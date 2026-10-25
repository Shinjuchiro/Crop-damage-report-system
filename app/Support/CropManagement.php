<?php

namespace App\Support;

/**
 * The tabs of the Crop Management Report: three for the office, two for an
 * association.
 *
 * WHY THIS IS NOT IN A CONTROLLER
 *
 * Because the tab strip sits on every one of those pages, not on a hub of
 * its own. Five controllers each describing the same tabs is five places for
 * them to start disagreeing about their own order, labels or icons.
 *
 * It used to hold three shared counts as well, one per tab, drawn as cards
 * above the strip. Those are gone: each page now shows its own figures in
 * that spot instead, so there is one row of numbers rather than two, and the
 * row belongs to whichever tab is open.
 */
class CropManagement
{
    /**
     * The tabs, in the order the work happens: a farmer plants, then
     * reports damage, then a technician verifies it.
     *
     * TWO ROLES, ONE DEFINITION
     *
     * The association has the same report with two tabs rather than three.
     * Validation is a technician's work and the office's decision, and the
     * part an officer needs from it (where a member's report has got to)
     * already shows on the report itself, so a third tab there would be a
     * door to a page that does not exist for that role.
     *
     * Both sets live here, in one list, because they are the same section
     * of the system seen by two roles. Written as two separate lists they
     * would drift apart in label and in order, and the tab strip is
     * precisely where that shows.
     *
     * @param  string  $role  'mao' or 'association'
     * @return array<string, array{label:string, route:string, patterns:array, icon:string}>
     */
    public static function tabs(string $role = 'mao'): array
    {
        if ($role === 'association') {
            return [
                'planting' => [
                    'label'    => 'Crop Planted',
                    'route'    => 'association.planting.index',
                    'patterns' => ['association.planting.*'],
                    'icon'     => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16',
                ],
                'damage' => [
                    'label'    => 'Crop Damage',
                    'route'    => 'association.reports.index',
                    'patterns' => ['association.reports.*'],
                    'icon'     => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
                ],
                'validation' => [
                    'label'    => 'Validation Monitoring',
                    'route'    => 'association.validations.index',
                    'patterns' => ['association.validations.*'],
                    'icon'     => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
                ],
            ];
        }

        return [
            'planting' => [
                'label'    => 'Crop Planted',
                'route'    => 'mao.crop-planting.index',
                'patterns' => ['mao.crop-planting.*'],
                'icon'     => 'M12 21v-7M12 14c0-3.3 2.2-5.5 5.5-5.5C17.5 11.8 15.3 14 12 14zM12 14C12 10.7 9.8 8.5 6.5 8.5 6.5 11.8 8.7 14 12 14zM4 21h16',
            ],
            'damage' => [
                'label'    => 'Crop Damage',
                'route'    => 'mao.damage-reports.index',
                'patterns' => ['mao.damage-reports.*'],
                'icon'     => 'M14 3v4a1 1 0 001 1h4M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM12 11v3.5M12 17.5h.01',
            ],
            'validation' => [
                'label'    => 'Validation Monitoring',
                'route'    => 'mao.validations.index',
                'patterns' => ['mao.validations.*'],
                'icon'     => 'M9 4H7a2 2 0 00-2 2v13a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2M9 4a2 2 0 002 2h2a2 2 0 002-2M9 4a2 2 0 012-2h2a2 2 0 012 2m-6.5 9.5l2 2 4-4',
            ],
        ];
    }
}
