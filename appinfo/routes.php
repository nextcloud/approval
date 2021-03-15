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
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'approval#isApprovalPending', 'url' => '/{fileId}/is-pending', 'verb' => 'GET'],
        ['name' => 'approval#approve', 'url' => '/{fileId}/approve', 'verb' => 'PUT'],
        ['name' => 'approval#reject', 'url' => '/{fileId}/reject', 'verb' => 'PUT'],
        ['name' => 'approval#createTag', 'url' => '/tag', 'verb' => 'POST'],
    ]
];
