# Files approval

👍 Approve or reject files/folders with hidden tags.

In the files sidebar, authorized users will see approval buttons for files with pending approval.
All users will see if a file approval is pending or if it has been approved or rejected.

## Settings

There is an Approval admin additional settings section where you can add rules defining who can approve what.
A rule is composed with:
* A list of authorized users
* Pending tag: the tag meaning elements can be approved or rejected
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
