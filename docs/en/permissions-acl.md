# Permissions and ACL

ContentBuilder NG combines Joomla groups with a permission matrix for each view.

## Separate contexts

Each view has separate **frontend** and **backend** permissions. Granting a backend
permission does not automatically grant its frontend equivalent.

## Joomla group inheritance

The permission service considers the user's direct groups and their parent groups.
Permissions assigned to a parent can therefore be inherited. Repository unit tests
cover this behavior for both frontend and backend.

## Available actions

| Permission | Effect |
| --- | --- |
| View | Open record details |
| New | Create a record |
| Edit | Modify a record |
| Delete | Delete a record |
| State | Change a custom state |
| Publish | Publish or unpublish |
| Full article | Change article settings |
| Language | Change record language |
| Rating | Rate a record |
| API | Use API endpoints requiring it |
| Stats | Read view statistics |
| List Access | Open the list |

## Own-record permissions

The **Own** section grants actions only for records belonging to the user. Ownership
depends on source type; some anonymous submissions can also depend on the session.

“Own records only” filters the displayed data. It is different from an own-record
permission: one limits the dataset, while the other authorizes an action.

## Limits and verification

A checked permission can still be refused when the view is unpublished, a
creation/edit limit is reached, required verification is invalid, the user does not
own the record, or the source is missing. Global view limits can be replaced by
individual user limits.

## Role examples

### Guest

- List Access and View only for intentionally public data;
- New only when public submission is required;
- no Edit, Delete, Publish, API, or Stats;
- use captcha and validation for public submission.

### Registered user

- List Access, View, and New;
- Edit and Delete for own records only;
- no Publish;
- API only for an explicit integration requirement.

### Editor

- List Access, View, New, and Edit;
- State and Publish when moderating;
- Delete according to policy;
- Full article only when responsible for categories, access, and dates.

### Administrator

Administrators can receive all backend permissions. Do not assume that Super User
status bypasses every view rule; test the actual matrix.

## API permissions

| Request | Required permissions |
| --- | --- |
| GET details | API + View |
| GET list | API + View + List Access |
| PUT/PATCH/POST update | API + Edit |
| `action=get-unique-values` | API + List Access |
| `action=rating` | API + Rating |
| `action=stats` | Stats only |

Each exposed field must also be published and marked **API allowed**.

## User cannot see the list

- [ ] view published
- [ ] source valid
- [ ] frontend context enabled
- [ ] List Access granted
- [ ] View granted
- [ ] direct or inherited group configured
- [ ] menu points to the correct view
- [ ] language and filters checked
- [ ] own-record restriction understood

## User cannot edit

- [ ] frontend Edit permission
- [ ] field marked Editable
- [ ] editable template configured
- [ ] record ownership checked
- [ ] edit limit not reached
- [ ] verification valid
- [ ] record and source exist
- [ ] Edit button enabled

## Debugging permissions

Temporarily enable **Show permissions**, **Show active filters**, and **Show request
logs**. Compare an allowed account with a refused account, then disable Debug.
