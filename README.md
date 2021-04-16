# Files approval

👍 Approve or reject files/folders with hidden tags.

In the files sidebar, authorized users will see approval buttons for files with pending approval.
All users having access to the file/folder will see if a file approval is pending or if it has been approved or rejected.

## Settings

There is an Approval section in Flow admin settings where you can add rules defining who can approve what.
A rule is composed with:
* A list of authorized users, groups and circles
* A pending tag: the tag meaning elements can be approved or rejected
* An approved tag: the assigned tag when elements get approved
* A rejected tag: the assigned tag when elements get rejected

A pending tag can only be used in one approval rule.
Rules can be chained. For example, if the approved tag of a rule A is used as the pending tag of another rule B,
then once a file is approved by the first rule, it becomes pending for the second one.

## Tag assignment

There are two ways to assign a hidden tag to a file/folder:

* Admin can see and use hidden tags like classic tags. So they can be manually assigned.
* Via the Files Automated Tagging App.

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

![1](https://github.com/eneiluj/approval/raw/master/img/screenshot_1.jpg)
![2](https://github.com/eneiluj/approval/raw/master/img/screenshot_2.jpg)
![3](https://github.com/eneiluj/approval/raw/master/img/screenshot_3.jpg)