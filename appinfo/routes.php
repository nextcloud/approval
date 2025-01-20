<?php

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		/**
		 * Settings
		 */
		['name' => 'Config#getRules', 'url' => '/rules', 'verb' => 'GET'],
		['name' => 'Config#saveRule', 'url' => '/rule/{id}', 'verb' => 'PUT'],
		['name' => 'Config#deleteRule', 'url' => '/rule/{id}', 'verb' => 'DELETE'],
		['name' => 'Config#createRule', 'url' => '/rule', 'verb' => 'POST'],
		['name' => 'Config#createTag', 'url' => '/tag', 'verb' => 'POST'],
	],
	'ocs' => [
		/**
		 * Approval actions
		 */
		[
			'name' => 'Approval#getUserRequesterRules',
			'url' => '/api/{apiVersion}/user-requester-rules',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Approval#getPendingNodes',
			'url' => '/api/{apiVersion}/pendings',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Approval#getApprovalState',
			'url' => '/api/{apiVersion}/state/{fileId}',
			'verb' => 'GET',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Approval#approve',
			'url' => '/api/{apiVersion}/approve/{fileId}',
			'verb' => 'PUT',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Approval#reject',
			'url' => '/api/{apiVersion}/reject/{fileId}',
			'verb' => 'PUT',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
		[
			'name' => 'Approval#request',
			'url' => '/api/{apiVersion}/request/{fileId}/{ruleId}',
			'verb' => 'POST',
			'requirements' => [
				'apiVersion' => 'v1',
			],
		],
	],
];
