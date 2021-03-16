<?php
/**
 * Nextcloud - Approval
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2021
 */

return [
    'routes' => [
        ['name' => 'config#getSettings', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'config#saveSetting', 'url' => '/setting/{id}', 'verb' => 'PUT'],
        ['name' => 'config#deleteSetting', 'url' => '/setting/{id}', 'verb' => 'DELETE'],
        ['name' => 'config#createSetting', 'url' => '/setting', 'verb' => 'POST'],

        ['name' => 'approval#getApprovalState', 'url' => '/{fileId}/state', 'verb' => 'GET'],
        ['name' => 'approval#approve', 'url' => '/{fileId}/approve', 'verb' => 'PUT'],
        ['name' => 'approval#reject', 'url' => '/{fileId}/reject', 'verb' => 'PUT'],
        ['name' => 'approval#createTag', 'url' => '/tag', 'verb' => 'POST'],
    ]
];
