# Important

The Approval App will no longer be maintained for Nextcloud versions 28 and above, users are encouraged to transition to https://apps.nextcloud.com/apps/integration_docusign.
If you are a customer and rely on features not available in DocuSign and are planning to upgrade, please open a support ticket before upgrading so we can assist you.
We appreciate your understanding and are committed to helping you through this transition.

# Files approval

✔ Approve or reject files/folders.

This app is integrated in the files list (Files app).

Admins can define approval workflows: who can request, who can approve/reject.
Users can then request approval. Authorized users will be able to approve or reject.

Files can optionally be signed with DocuSign or LibreSign.

## Settings

There is an Approval admin settings section where you define workflows.

Workflow definition:

* A workflow title
* A list of users, groups and circles who can request approval
* A list of users, groups and circles who can approve or reject
* A pending tag: the tag meaning approval was requested and elements can be approved or rejected
* An approved tag: the assigned tag when elements get approved
* A rejected tag: the assigned tag when elements get rejected

A pending tag can only be used in one approval workflow.
Workflows can be chained. For example, if the approved tag of a workflow A is used as the pending tag of another workflow B,
then once a file is approved by the workflow A, it becomes pending for the B one.

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
