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
        ['name' => 'config#getRules', 'url' => '/rules', 'verb' => 'GET'],
        ['name' => 'config#saveRule', 'url' => '/rule/{id}', 'verb' => 'PUT'],
        ['name' => 'config#deleteRule', 'url' => '/rule/{id}', 'verb' => 'DELETE'],
        ['name' => 'config#createRule', 'url' => '/rule', 'verb' => 'POST'],

        ['name' => 'approval#getApprovalState', 'url' => '/{fileId}/state', 'verb' => 'GET'],
        ['name' => 'approval#approve', 'url' => '/{fileId}/approve', 'verb' => 'PUT'],
        ['name' => 'approval#reject', 'url' => '/{fileId}/reject', 'verb' => 'PUT'],
        ['name' => 'approval#createTag', 'url' => '/tag', 'verb' => 'POST'],
    ]
];
