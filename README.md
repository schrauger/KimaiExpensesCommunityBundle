# KimaiExpensesCommunityBundle

A free, self-hosted expense plugin for [Kimai](https://www.kimai.org). It follows the same basic model as Kimai's commercial Expenses plugin and adds a few things that plugin does not have, such as receipt attachments.

This is an independent implementation based on Kimai's public plugin APIs and documented behavior. It does **not** copy source code from the commercial Expenses plugin.

## Features

- **Categories** with a unit (mile, night, item ...), a default cost per unit, an optional colour, help text, and a default description
- **quantity × cost** totals; negative quantities are allowed for credits and corrections
- Date and time, user, customer / project / activity, description, billable flag
- **Kimai-style UI:** modal create/edit, filter toolbar, pagination, totals for the whole filtered list
- **My expenses** and **All expenses** (the latter needs `view_other_timesheet`)
- **Invoices:** billable, not-yet-exported expenses appear in Kimai's invoice screen and are marked exported when the invoice is created
- **Export state:** an "Exported" checkbox in the edit form and bulk "Mark as (not) exported" in the list; exported records are locked like Kimai timesheets
- **Receipts:** attach a PDF/JPG/PNG/WebP to an expense, view or remove it later
- **CSV export** of the current filter (formula-injection safe, opens cleanly in Excel)

### Categories vs. Activities

They look similar but do different jobs. A Kimai **Activity** describes *work* and carries hourly/fixed rates. An expense **Category** is a *price list entry*: a unit, a default cost per unit, help text and a default description. An expense can have both: the category prices it, the optional activity says what the money was spent on.

## Compatibility

- Kimai: **2.65+**
- PHP: **8.2+** (with the `fileinfo` extension, used to verify receipt types)

## Install / update

Copy this directory to:

```text
var/plugins/KimaiExpensesCommunityBundle
```

Then from the Kimai application directory:

```bash
bin/console kimai:reload -n
bin/console kimai:bundle:kimai-expenses-community:install
```

The install command runs all migrations, so run it again after every update (version 0.2.0 adds receipt columns and a category colour). If Kimai does not show the plugin after copying it in, make sure this file exists:

```text
var/plugins/KimaiExpensesCommunityBundle/KimaiExpensesCommunityBundle.php
```

## First setup

The first migration creates a **Mileage** category (unit `mile`, default cost `0.70`). Change the rate under **Expenses → Categories** before recording real mileage; the plugin does not assume a particular reimbursement rate.

## Permissions

The plugin registers these permissions. They are initially assigned to `ROLE_SUPER_ADMIN`; configure other roles under **System → Roles**.

| Permission | Allows |
| --- | --- |
| `view_kimai_expenses_community` | See the Expenses pages (own expenses) |
| `create_kimai_expenses_community` | Create expenses |
| `edit_kimai_expenses_community` | Edit expenses; attach or remove receipts |
| `delete_kimai_expenses_community` | Delete expenses |
| `edit_kimai_expenses_community_cost` | Change the cost per unit of an expense |
| `edit_export_kimai_expenses_community` | Change the exported flag (single or bulk) |
| `edit_exported_kimai_expenses_community` | Edit or delete expenses that are already exported |
| `export_kimai_expenses_community` | Download the list as CSV |
| `manage_kimai_expenses_community_category` | Manage categories |

As with Kimai timesheets, users without `view_other_timesheet` only see and change their own expenses.

## Invoicing notes

- An expense appears on an invoice only if it is **billable**, has a **project**, and is **not exported**. The form therefore requires a project for billable expenses.
- Choosing a project also sets the customer, so the two can never disagree.
- Invoice templates can use `entry.type == 'expense'` and `entry.category` to treat expense lines differently.
- Un-exporting an expense (see above) makes it available for invoicing again.

## Receipts: storage, limits, backups

- Files are stored in `var/data/kimai-expenses-community/receipts/` (outside the web root) under random names and are only served to users allowed to see the expense.
- **Include that folder in your backups**, and, with Docker, make sure `var/data` is on a persistent volume.
- Maximum size is 10 MB per file, but PHP's `upload_max_filesize` and `post_max_size` apply first. PHP's default for `upload_max_filesize` is only 2 MB, so raise both if you want larger receipts.
- Deleting an expense deletes its receipt file.

## Timezones

Expense dates are stored in UTC. They are shown, filtered and invoiced in the timezone of the user, so "September 30" in a filter or on an invoice means the user's local September 30.

## Known limitations / ideas for later

- No REST API endpoints yet (the commercial plugin has them)
- No custom fields, tags, budget integration, or per-customer/project summary pages
- CSV only; no Excel/PDF export
- UI text is English only (no translation files yet)
- Visibility ignores Kimai team membership; it uses `view_other_timesheet` only
