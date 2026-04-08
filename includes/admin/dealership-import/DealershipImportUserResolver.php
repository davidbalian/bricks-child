<?php
/**
 * Resolves CSV dealership name to a single WP user (dealership role).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DealershipImportUserResolver
{
    /** @var array<string, list<int>> */
    private $normalized_to_user_ids = array();

    public function __construct()
    {
        $this->buildIndex();
    }

    public static function normalizeLabel(string $label): string
    {
        $label = trim(wp_strip_all_tags($label));
        if (function_exists('mb_strtolower')) {
            $label = mb_strtolower($label, 'UTF-8');
        } else {
            $label = strtolower($label);
        }
        $label = preg_replace('/\s+/u', ' ', $label);
        $label = preg_replace('/[^\p{L}\p{N}\s]/u', '', $label);

        return trim($label);
    }

    private function buildIndex(): void
    {
        $users = get_users(
            array(
                'role'   => 'dealership',
                'number' => -1,
                'fields' => 'all',
            )
        );
        if (!is_array($users)) {
            return;
        }

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }
            $uid = (int) $user->ID;
            $labels = array(
                (string) get_user_meta($uid, 'dealership_name', true),
                (string) get_user_meta($uid, 'first_name', true),
                (string) $user->display_name,
            );
            $norms_for_user = array();
            foreach ($labels as $raw) {
                $n = self::normalizeLabel($raw);
                if ($n === '') {
                    continue;
                }
                $norms_for_user[$n] = true;
            }
            foreach (array_keys($norms_for_user) as $n) {
                if (!isset($this->normalized_to_user_ids[$n])) {
                    $this->normalized_to_user_ids[$n] = array();
                }
                if (!in_array($uid, $this->normalized_to_user_ids[$n], true)) {
                    $this->normalized_to_user_ids[$n][] = $uid;
                }
            }
        }
    }

    /**
     * @return array{status:string,user_id?:int,user_ids?:list<int>}
     */
    public function resolve(string $name_from_csv): array
    {
        $n = self::normalizeLabel($name_from_csv);
        if ($n === '') {
            return array('status' => 'unmatched');
        }
        $ids = isset($this->normalized_to_user_ids[$n]) ? $this->normalized_to_user_ids[$n] : array();
        if (count($ids) === 0) {
            return array('status' => 'unmatched');
        }
        if (count($ids) > 1) {
            return array(
                'status'  => 'ambiguous',
                'user_ids' => $ids,
            );
        }

        return array(
            'status'  => 'matched',
            'user_id' => (int) $ids[0],
        );
    }
}
