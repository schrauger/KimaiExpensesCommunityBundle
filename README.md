# KimaiExpensesCommunityBundle

A small, free, self-hosted Kimai plugin for tracking expenses using the same basic model documented for Kimai's commercial Expenses functionality:

- categories with a default cost per unit
- quantity × cost calculation
- date/time
- user
- customer, project and activity links
- description
- billable flag
- exported flag in the database for later invoice/export work
- role permissions

This is an independent implementation based on Kimai's public plugin APIs and documented behavior. It does **not** copy source code from the commercial Expenses plugin.

## Compatibility

- Kimai: **2.65+**
- PHP: **8.2+**

## Install

Copy this directory to:

```text
var/plugins/KimaiExpensesCommunityBundle
```

Then from the Kimai application directory:

```bash
bin/console kimai:reload -n
bin/console kimai:bundle:kimai-expenses-community:install
```

If Kimai does not immediately show the plugin after copying it in, make sure the directory is exactly:

```text
var/plugins/KimaiExpensesCommunityBundle/KimaiExpensesCommunityBundle.php
```

## First setup

The first migration creates a **Mileage** category as a starter example with:

- unit: `mile`
- default rate: `1.00`

Change the default rate under **Expenses → Categories** before recording real mileage. `1.00` is intentionally only a safe placeholder; the plugin does not assume a particular reimbursement rate.

## Permissions

The plugin registers these permissions:

- `view_kimai_expenses_community`
- `create_kimai_expenses_community`
- `edit_kimai_expenses_community`
- `delete_kimai_expenses_community`
- `edit_kimai_expenses_community_cost`
- `manage_kimai_expenses_community_category`
- `edit_exported_kimai_expenses_community`

The permissions are initially assigned to `ROLE_SUPER_ADMIN`. Other roles can be configured in **System → Roles**.

As with Kimai's documented expense behavior, users without `view_other_timesheet` only see and modify their own expenses.

