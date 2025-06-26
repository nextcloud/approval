<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Files approval

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/approval)](https://api.reuse.software/info/github.com/nextcloud/approval)

✔ Approve or reject files/folders.

**Warning**: The DocuSign integration is no longer part of this app
and can be installed with [this app](https://apps.nextcloud.com/apps/integration_docusign).

This app is integrated in the files list (Files app).

Admins can define approval workflows: who can request, who can approve/reject.
Users can then request approval. Authorized users will be able to approve or reject.

## Settings

After installing the app, in Administration settings, there is a section "Approval workflows" where you define workflows.

Workflow definition:

* A workflow title
* A list of users, groups and circles who can request approval
* A list of users, groups and circles who can approve or reject
* A pending tag: the tag meaning approval was requested and elements can be approved or rejected
* An approved tag: the assigned tag when elements get approved
* A rejected tag: the assigned tag when elements get rejected

A pending tag can only be used in one approval workflow.
Workflows can be chained. For example, if the approved tag of a workflow A is used as the pending tag of another workflow B,
then once a file is approved by the workflow A, it becomes pending for the B one. For an example of a chain involving a leave
request that needs to be approved by a manager and then the department head, there is a screenshot below.

![Workflow chain](https://github.com/nextcloud/approval/raw/main/img/screenshot_chained.jpg)

## Tag assignment

There are 3 ways to assign a hidden tag to a file/folder:

* Click the "Request approval" button in Files sidebar
* Admins can see and use hidden tags like classic tags. So they can be manually assigned.
* Via the [Files Automated Tagging](https://github.com/nextcloud/files_automatedtagging) App

## Build

Just run:
```
make dev
```
or for production:
```
make build
```

## Screenshots

![1](https://github.com/nextcloud/approval/raw/main/img/screenshot_1.jpg)
![2](https://github.com/nextcloud/approval/raw/main/img/screenshot_2.jpg)
![3](https://github.com/nextcloud/approval/raw/main/img/screenshot_3.jpg)
