# Files approval

👍 Approve or reject files/folders with hidden tags.

Authorized users will see approval buttons in the files sidebar for files with pending approval.
All users will see if a file has been approved or rejected in the files sidebar too.

## Settings

There is an Approval admin additional settings section where you can add rules defining who can approve what.
A rule is composed with:
* A list of authorized users
* Pending tag: the tag meaning a files can be approved or rejected
* Approved tag: the assigned tag when elements are approved
* Rejected tag: the assigned tag when elements are rejected


## Build

Just run:
```
make dev
```
or for production:
```
make build
```