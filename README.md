# MileageExpenseBundle — Phase 1

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

The `composer.json` requires Kimai `2.65.0` or newer using Kimai's integer plugin version format (`26500`).

## Install

Copy this directory to:

```text
var/plugins/MileageExpenseBundle
```

Then from the Kimai application directory:

```bash
bin/console kimai:reload -n
bin/console kimai:bundle:mileage-expense:install
```

If Kimai does not immediately show the plugin after copying it in, make sure the directory is exactly:

```text
var/plugins/MileageExpenseBundle/MileageExpenseBundle.php
```

## First setup

The first migration creates a **Mileage** category as a starter example with:

- unit: `mile`
- default rate: `1.00`

Change the default rate under **Expenses → Categories** before recording real mileage. `1.00` is intentionally only a safe placeholder; the plugin does not assume a particular reimbursement rate.

## Permissions

The plugin registers these permissions:

- `view_mileage_expense`
- `create_mileage_expense`
- `edit_mileage_expense`
- `delete_mileage_expense`
- `edit_mileage_expense_cost`
- `manage_mileage_expense_category`
- `edit_exported_mileage_expense`

The permissions are initially assigned to `ROLE_SUPER_ADMIN`. Other roles can be configured in **System → Roles**.

As with Kimai's documented expense behavior, users without `view_other_timesheet` only see and modify their own expenses.

## Phase 1 limitations

This first version intentionally does **not** implement:

- Kimai invoice integration
- Kimai budget integration
- advanced data-table filtering/search
- CSV/XLSX/PDF exports
- API endpoints
- receipt attachments
- dynamic Customer → Project → Activity filtering
- automatic exported-state handling during invoice generation

Those belong in the next phases once the basic workflow is confirmed on the target Kimai installation.

## Development notes

The plugin keeps its own Doctrine migration history in `bundle_migration_mileage_expense`. Future schema changes should be added as new migration classes; do not edit the initial migration once it has been deployed.
