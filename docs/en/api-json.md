# JSON API

The API endpoint is:

```text
index.php?option=com_contentbuilderng&task=api.display&id=VIEW_ID
```

Add `format=json` when required by the Joomla routing or integration context.

## Security principles

- the view must exist;
- view permissions are enforced;
- fields must be published;
- each exposed field must be marked **API allowed**;
- required permissions depend on the operation;
- signed administrator preview links are temporary.

## Response envelope

Success:

```json
{
  "success": true,
  "messages": [],
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "messages": ["Error message"],
  "data": null
}
```

HTTP error status is set for codes from 400 to 599.

## Read a list

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&list[limit]=20&list[start]=0
```

Permissions: **API + View + List Access**.

```json
{
  "success": true,
  "messages": [],
  "data": {
    "items": [
      {
        "record_id": 123,
        "values": {
          "Name": "Example"
        }
      }
    ],
    "pagination": {
      "total": 1,
      "limit": 20,
      "start": 0
    }
  }
}
```

Only API-authorized fields appear in `values`.

## Read record details

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&record_id=123
```

Permissions: **API + View**.

Default format:

```json
{
  "success": true,
  "messages": [],
  "data": {
    "record_id": 123,
    "form_id": 3,
    "fields": {
      "Name": "Example"
    },
    "navigation": {
      "previous": 122,
      "next": 124
    }
  }
}
```

With `verbose=1`, each field contains:

```json
{
  "reference_id": "17",
  "label": "Name",
  "value": "Example"
}
```

## Update a record

Accepted methods: `PUT`, `PATCH`, and `POST`.

```text
/index.php?option=com_contentbuilderng&task=api.display&id=3&record_id=123
```

Payload:

```json
{
  "fields": {
    "Name": "New name",
    "Email": "contact@example.test"
  }
}
```

Permissions: **API + Edit**.

`record_id` is required. Keys can be field names or recognized numeric field
references. Unauthorized fields are ignored; the request is refused when no
authorized field remains.

Creating a new record through this API is not demonstrated by the controller:
**To verify**. The current code requires `record_id` for `POST`.

## Unique values

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=get-unique-values&field_reference_id=17
```

Parameters:

- `field_reference_id`: requested field reference;
- `where_field`: optional condition field;
- `where`: optional condition value.

Permissions: **API + List Access**. Both referenced fields must be API-authorized.

Response:

```json
{
  "success": true,
  "messages": [],
  "data": {
    "code": 0,
    "field_reference_id": "17",
    "msg": ["Value A", "Value B"]
  }
}
```

## Rating

```text
POST /index.php?option=com_contentbuilderng&task=api.display&id=3&action=rating&record_id=123&rate=5
```

Permissions: **API + Rating**.

Methods other than `POST` are refused. The rating level count comes from the view
(`rating_slots`). The controller uses session and IP information to limit repeated
votes.

> ⚠️ **Warning:** the `rating` action requires a valid **Joomla CSRF token**. The
> controller calls `Session::checkToken` (in `post` or `get`) and returns a
> `JINVALID_TOKEN` (403) error when the token is missing or invalid. An external caller
> must therefore hold an authenticated Joomla session and send the form token.

## Statistics

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=stats
```

Permission: **Stats only**.

```json
{
  "success": true,
  "messages": [],
  "data": {
    "form": {
      "id": 3,
      "name": "Contacts",
      "title": "Public contacts"
    },
    "records": {
      "total": 31,
      "published": 9,
      "unpublished": 22,
      "future": 0,
      "edited": 5,
      "scheduled": 0,
      "expired": 0,
      "last_update": "2026-06-04 19:01:43"
    },
    "ratings": {
      "rated_records": 0,
      "rating_count": 0,
      "rating_sum": 0,
      "average": 0
    },
    "languages": {
      "*": 31
    }
  }
}
```

### Group by field

```text
&action=stats&field=Route
```

The field can be resolved by reference, name, or label, but must be published and
API-authorized.

When every distinct value of the field is numeric, the `field` payload also
returns the aggregates `sum` (weighted by record counts), `min` and `max`;
otherwise these three keys are `null`.

### Filter

```text
&action=stats&filter[field]=Route&filter[value]=200%20km*
```

Rules:

- leading and trailing spaces are ignored;
- `*` matches any character sequence;
- `|` separates alternatives.

Example:

```text
filter[value]=200 km* | 300 km*
```

## Sparse fieldsets

For `GET` requests:

```text
&fields[items]=record_id,Name,Email
&fields[fields]=Name,Email
&fields[records]=total,published
&fields[ratings]=average
```

Top-level resources not named in `fields[...]` are removed. Request several resources
with several parameters.

```text
GET /index.php?option=com_contentbuilderng&task=api.display&id=3&action=stats&fields[records]=total&fields[ratings]=average
```

## Common errors

| Message | Probable cause |
| --- | --- |
| View not found | Wrong ID or missing view |
| BF View not found | Missing BreezingForms source |
| API access denied | Missing API permission |
| Statistics access denied | Missing Stats permission |
| Field is not allowed for API/Stats | Field unpublished or API option disabled |
| `record_id` is required | Update without an identifier |
| No fields provided | Missing or invalid payload |

## Authentication

The API uses the Joomla identity and session attached to the request. The inspected
files do not document a standalone permanent API-token mechanism: **To verify** for
the authentication system deployed on the site.
