<?php

namespace App\Http\Controllers;

use App\Enums\SubscriptionType;
use App\Models\Agreement;
use App\Support\DemoContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The customer's add-on cover. Each add-on is a row in the subscriptions table,
 * so starting, stopping and restarting are all tracked. The catalogue (names,
 * prices, blurb) still comes from the demo content for now.
 */
class CoverController extends Controller
{
    /**
     * The cover catalogue with an `active` flag for the signed-in customer.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['cover' => $this->cover($this->agreementFor($request))]);
    }

    /**
     * Set which add-ons are active. Anything dropped is ended (kept for history),
     * anything new starts a fresh subscription. Returns the updated cover.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'types' => ['present', 'array'],
            'types.*' => [Rule::enum(SubscriptionType::class)],
        ]);

        $agreement = $this->agreementFor($request);

        if ($agreement === null) {
            return response()->json(['message' => 'No warranty on this account yet.'], 404);
        }

        $desired = $validated['types'];
        $active = $agreement->subscriptions()->active()->get();
        $activeTypes = $active->map(fn ($s) => $s->type->value)->all();

        // Stop the ones removed.
        foreach ($active as $subscription) {
            if (! in_array($subscription->type->value, $desired, true)) {
                $subscription->update(['ended_at' => now()->toDateString()]);
            }
        }

        // Start the ones added (a new row each time, so a restart is its own record).
        foreach ($desired as $value) {
            if (! in_array($value, $activeTypes, true)) {
                $type = SubscriptionType::from($value);
                $agreement->subscriptions()->create([
                    'type' => $type,
                    'monthly_price' => $this->priceFor($type),
                    'started_at' => now()->toDateString(),
                ]);
            }
        }

        return response()->json(['cover' => $this->cover($agreement)]);
    }

    private function agreementFor(Request $request): ?Agreement
    {
        return $request->user()->agreements()->latest()->first();
    }

    /**
     * Merge the catalogue with the agreement's active subscriptions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cover(?Agreement $agreement): array
    {
        $active = $agreement
            ? $agreement->subscriptions()->active()->get()->map(fn ($s) => $s->type->value)->all()
            : [];

        $catalogue = DemoContent::get('coverOptions') ?? [];

        return array_values(array_filter(array_map(function (array $option) use ($active) {
            $type = SubscriptionType::fromName($option['name'] ?? '');

            if ($type === null) {
                return null;   // catalogue item that isn't a real subscription type
            }

            return [
                ...$option,
                'type' => $type->value,
                'active' => in_array($type->value, $active, true),
            ];
        }, $catalogue)));
    }

    private function priceFor(SubscriptionType $type): float
    {
        foreach (DemoContent::get('coverOptions') ?? [] as $option) {
            if (SubscriptionType::fromName($option['name'] ?? '') === $type) {
                return (float) ($option['price'] ?? 0);
            }
        }

        return 0;
    }
}
