<?php
/** Name matching is discovery only. It never establishes account ownership. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class DealerDirectory
{
    private const CACHE = 'ag_dealer_directory_v1';

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(remove_accents(mb_substr($value, 0, 160)), 'UTF-8');
        $value = strtr($value, ['ά'=>'α','έ'=>'ε','ή'=>'η','ί'=>'ι','ό'=>'ο','ύ'=>'υ','ώ'=>'ω','ϊ'=>'ι','ϋ'=>'υ','ΐ'=>'ι','ΰ'=>'υ']);
        $value = strtr($value, ['α'=>'a','β'=>'v','γ'=>'g','δ'=>'d','ε'=>'e','ζ'=>'z','η'=>'i','θ'=>'th','ι'=>'i','κ'=>'k','λ'=>'l','μ'=>'m','ν'=>'n','ξ'=>'x','ο'=>'o','π'=>'p','ρ'=>'r','σ'=>'s','ς'=>'s','τ'=>'t','υ'=>'y','φ'=>'f','χ'=>'ch','ψ'=>'ps','ω'=>'o']);
        $value = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
        return trim(preg_replace('/(?:\s+(?:ltd|limited|llc|company|co))+$/u', '', $value));
    }

    public static function score(string $query, string $name): int
    {
        $a = self::normalize($query); $b = self::normalize($name);
        if ($a === '' || $b === '') { return 0; }
        if ($a === $b) { return 100; }
        $at = explode(' ', $a); $bt = explode(' ', $b);
        sort($at); sort($bt);
        $sortedA = implode(' ', $at); $sortedB = implode(' ', $bt);
        if ($sortedA === $sortedB) { return 98; }
        $distance = min(levenshtein($a, $b), levenshtein($sortedA, $sortedB));
        $edit = 100 * (1 - $distance / max(strlen($a), strlen($b)));
        $shared = count(array_intersect($at, $bt));
        $tokens = $shared ? 100 * 2 * $shared / (count($at) + count($bt)) : 0;
        $prefix = mb_strlen($a) >= 4 && (str_contains($b, $a) || str_contains($a, $b)) ? 78 : 0;
        return (int) round(min(97, max($edit, $tokens, $prefix)));
    }

    public static function bootstrap(): void
    {
        foreach (['user_register', 'profile_update', 'deleted_user', 'set_user_role'] as $hook) {
            add_action($hook, static fn () => delete_transient(self::CACHE));
        }
        foreach (['added_user_meta', 'updated_user_meta', 'deleted_user_meta'] as $hook) {
            add_action($hook, static function ($meta, $user, $key) {
                if (in_array($key, ['first_name','last_name','dealership_name','car_city','city','_ag_dealer_showroom',Review::USER_META,'_autoagora_dealer_api_blocked'], true) || str_ends_with($key, 'capabilities')) {
                    delete_transient(self::CACHE);
                }
            }, 10, 3);
        }
    }

    private static function entries(): array
    {
        $cached = get_transient(self::CACHE);
        if (is_array($cached)) { return $cached; }
        $entries = [];
        // Page through the whole account catalogue before ranking, never SQL-LIMIT a fuzzy search.
        for ($offset = 0; ; $offset += 200) {
            $users = get_users(['role' => 'dealership', 'number' => 200, 'offset' => $offset,
                'orderby' => 'ID', 'order' => 'ASC', 'count_total' => false]);
            update_meta_cache('user', array_map(static fn ($u) => (int) $u->ID, $users));
            foreach ($users as $user) {
                if (user_can($user, 'manage_options') || get_user_meta($user->ID, Review::USER_META, true)) { continue; }
                $names = array_values(array_unique(array_filter(array_map('trim', [
                    (string) get_user_meta($user->ID, 'dealership_name', true),
                    trim($user->first_name . ' ' . $user->last_name), (string) $user->display_name,
                ]), static fn ($n) => $n !== '' && !preg_match('/^[+\d\s-]+$/D', $n))));
                if (!$names) { continue; }
                $showroom = get_user_meta($user->ID, '_ag_dealer_showroom', true);
                $city = is_array($showroom) ? (string) ($showroom['city'] ?? '') : '';
                if ($city === '') { $city = (string) (get_user_meta($user->ID, 'car_city', true) ?: get_user_meta($user->ID, 'city', true)); }
                $entries[] = ['id' => (int) $user->ID, 'name' => $names[0], 'names' => $names, 'city' => $city];
            }
            if (count($users) < 200) { break; }
        }
        set_transient(self::CACHE, $entries, 5 * MINUTE_IN_SECONDS);
        return $entries;
    }

    public static function search(string $name, string $city = '', bool $internal = false): array
    {
        if ($internal && !Review::admin()) { return []; }
        $normalized = self::normalize($name);
        if (mb_strlen($normalized) < 3 || mb_strlen($name) > 120) { return []; }
        $matches = [];
        foreach (self::entries() as $entry) {
            // Staging's eligibility allowlist also prevents exposing cloned real accounts.
            if (!$internal && !Support::eligible($entry['id'])) { continue; }
            $score = max(array_map(static fn ($alias) => self::score($name, $alias), $entry['names']));
            if ($score < 68) { continue; }
            $cityMatch = $city !== '' && self::normalize($city) === self::normalize($entry['city']);
            unset($entry['names']);
            $matches[] = $entry + ['score' => $score, 'city_match' => $cityMatch];
        }
        usort($matches, static fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['city_match'] <=> $a['city_match']) ?: ($a['id'] <=> $b['id']));
        return array_slice($matches, 0, $internal ? 10 : 5);
    }
}
