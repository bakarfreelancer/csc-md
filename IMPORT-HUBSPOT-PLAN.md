# CSC Members Portal — Import & HubSpot Integration Plan

## Overview

This document covers two separate but related features:

1. **Bulk Import** — an admin tool to import existing users and companies from a spreadsheet (CSV or Excel).
2. **HubSpot Integration** — automatically syncing new portal registrations to HubSpot, and optionally importing contacts from HubSpot into the portal.

---

## Part 1 — Bulk Import

### What we are importing

We have an existing CSV export from HubSpot (a survey/form response file) with **107 respondents** across **90 unique companies**. The file contains:

| CSV Column | Maps to portal field |
|---|---|
| First name | User first name |
| Last name | User last name |
| Email | WordPress user login/email |
| Phone number | `_csc_phone` |
| Company | Organisation name (create if not exists) |
| Job Title | `_csc_job_title` |
| Directory consent (col 1) | `_csc_consent_directory`, `_csc_dir_org_visible`, `_csc_dir_profile_visible` |
| Sharing consent (col 14) | `_csc_consent_sharing` |
| Newsletter preferences (cols 8–12) | `_csc_notif_newsletter` |

What the CSV does **not** contain that we will need defaults for:
- Password (users will be sent a set-password link on import)
- Organisation details (address, industry, IGP, website — these will be blank and can be filled in later by the company admin)
- Status — imported users should be set to `approved` immediately (they have already applied via the survey)

---

### Admin UI — Import Page

A new sub-page will be added under the **CSC Members** admin menu called **"Import Users / Companies"**.

The page will have two sections:

#### Section A — Upload & Preview

1. A file upload field accepting `.csv` and `.xlsx` files.
2. A **"Preview Import"** button — this parses the file and shows a preview table of what will be created/updated, without actually saving anything yet. This gives the admin a chance to spot problems before committing.
3. The preview table will show, for each row:
   - Name, Email, Company, Job Title
   - Whether a user with that email **already exists** in WordPress (flagged in orange)
   - Whether a company with that name **already exists** as an organisation post (flagged in orange)
   - The current proposed action: "Create user + company", "Create user, link to existing company", "Skip (user exists)", etc.

#### Section B — Import Options (shown after preview)

Before confirming the import the admin chooses the following per-import settings:

| Option | Description |
|---|---|
| **Default status** | Approved / Pending. Almost always "Approved" for existing members being migrated. |
| **Send set-password email** | Yes / No. If Yes, each new user receives the standard approval email with a link to set their password. If No, accounts are created without notifying users (useful if you are doing a staged rollout). |
| **Mark user as company admin** | Yes / No (default Yes). If Yes, the first imported user for each company is automatically assigned `_csc_can_edit_company = 1`. |
| **Sync to HubSpot** | Yes / No. If the HubSpot integration is configured, each newly created user can optionally be pushed to HubSpot as a contact at import time. Set to No for users who already exist in HubSpot (the case for this particular CSV file, since it came from HubSpot). |
| **Skip existing users** | Yes / No. If Yes (default), rows whose email already exists in WordPress are silently skipped. If No, the import updates the existing user's meta data instead. |

These options can also be **overridden per row** via an extra column in the spreadsheet. The supported optional columns that can be added to the CSV are:

| Column name in CSV | Effect |
|---|---|
| `is_company_admin` | `1` = grant company edit access, `0` = do not |
| `sync_to_hubspot` | `1` = push to HubSpot, `0` = skip HubSpot sync for this row |
| `send_email` | `1` = send set-password email, `0` = do not send |
| `status` | `approved` or `pending` — overrides the default for this row |

This way the admin can prepare the spreadsheet with per-row control before uploading.

---

### Import Process (what happens when "Confirm Import" is clicked)

The import runs in **batches of 20 rows per request** to avoid PHP timeouts. A progress bar on the admin page updates in real time via AJAX.

For each row the system does the following in order:

1. **Validate the row** — skip if email is missing or malformed.
2. **Check for existing user** — if email exists and "Skip existing" is on, log "Skipped" and move on.
3. **Find or create the organisation post** — search for a `csc_organisation` post whose title exactly matches the company name. If found, use it. If not, create a new published organisation post with the company name.
4. **Create the WordPress user** — with a random password (the user will set their own via the email link). Set `_csc_status` to the chosen default.
5. **Save all user meta** — phone, job title, organisation ID, consent fields, directory visibility, security defaults (2FA off, login alerts off), company admin flag.
6. **Optionally send the set-password email** — using the same template as the manual approval flow.
7. **Optionally push to HubSpot** — if the HubSpot integration is configured and the row's `sync_to_hubspot` flag is `1`.
8. **Log the result** — "Created", "Skipped", "Updated", or "Error: [reason]".

After the full import a **results summary** is shown: how many users created, companies created, skipped, errors. A downloadable CSV log is also offered so the admin can review every row outcome.

---

### Column Mapping for the Current HubSpot CSV

Because the current file (the survey export) has non-standard column names, the import tool will include a **column mapping step** between upload and preview. The admin maps each CSV column to its portal field using a dropdown. The mapping for the current file would be:

| CSV Column | Maps to |
|---|---|
| `First name` | First name |
| `Last name` | Last name |
| `Email` | Email (required) |
| `Phone number` | Phone |
| `Company` | Organisation name |
| `Section 1. Member Directory 3. What's your Job Title?` | Job title |
| `Section 1. 1. Would you like to be included in the Member Directory?` | Directory consent |
| `We would like to share your details with the Celtic Sea Cluster Board Members…` | Sharing consent |

The admin saves this mapping for reuse so that future uploads of the same format do not require re-mapping.

---

## Part 2 — HubSpot Integration

### Purpose

When a new user registers through the CSC Members Portal and is approved, their contact details should automatically appear in HubSpot so the Celtic Sea Cluster team can manage relationships, track engagement, and use HubSpot's marketing tools without duplicating data entry.

---

### HubSpot API Setup

The integration uses the **HubSpot Private App API** (not OAuth). The admin enters:

- **HubSpot Private App token** (a single API key string)

This is stored as a WordPress option (`csc_hubspot_token`) and entered via a new **"Integrations"** settings page in the CSC Members admin menu.

The settings page will also show:
- Connection status (green tick if the token is valid, red cross if not)
- A **"Test Connection"** button that pings the HubSpot API and confirms the token works
- A toggle: **"Sync new members to HubSpot automatically"** (on/off)

---

### What Gets Synced to HubSpot

When a member is approved (either manually by admin or automatically), the following data is sent to HubSpot as a **Contact**:

| HubSpot contact property | Source |
|---|---|
| `firstname` | User first name |
| `lastname` | User last name |
| `email` | User email (used as the unique identifier) |
| `phone` | `_csc_phone` |
| `jobtitle` | `_csc_job_title` |
| `company` | Organisation post title |
| `website` | Organisation `_csc_org_website` |

Additional custom HubSpot properties (to be created in HubSpot if they do not already exist):

| HubSpot property name | Value |
|---|---|
| `csc_member_status` | `approved` |
| `csc_portal_user_id` | WordPress user ID |
| `csc_organisation_id` | Organisation post ID |
| `csc_directory_visible` | `true` / `false` |

---

### Sync Triggers

| Event | Action |
|---|---|
| Admin clicks **Approve** on a member application | Create or update HubSpot contact |
| Member updates their profile (name, phone, job title) | Update HubSpot contact |
| Admin revokes access | Update `csc_member_status` to `revoked` in HubSpot |
| Bulk import with `sync_to_hubspot = 1` | Create or update HubSpot contact per row |

**Duplicate handling:** Before creating a new HubSpot contact the system searches by email. If a contact already exists it is **updated** (not duplicated). This is the correct behaviour for the current CSV import, where all contacts already exist in HubSpot.

---

### Error Handling & Logging

HubSpot API calls can fail (network issues, token expired, rate limits). The integration handles this gracefully:

- Failures are caught silently — they do not block the member approval or profile save.
- Failed sync attempts are logged to a WordPress option (`csc_hubspot_sync_errors`) with timestamp, user ID, and error message.
- The Integrations settings page shows the last 20 errors so the admin can see what went wrong.
- A **"Retry failed syncs"** button re-attempts any contacts that failed to sync.

---

## Implementation Sequence

We propose building in this order so each piece is independently usable:

### Phase 1 — HubSpot Settings Page
Set up the Integrations admin page, token storage, and test connection. No sync logic yet — just the infrastructure.

### Phase 2 — HubSpot Sync on Approval
Hook into the existing `approve_member` AJAX action to push the contact to HubSpot when a member is approved. This is the most impactful piece and requires no import work.

### Phase 3 — Bulk Import Tool (Preview + Confirm)
Build the file upload, column mapping, preview table, and the batched import AJAX process. Initially without HubSpot sync.

### Phase 4 — Per-Row HubSpot Sync in Import
Add the `sync_to_hubspot` column support to the import tool, calling the same sync function built in Phase 2.

### Phase 5 — HubSpot Sync on Profile Update
Hook into the profile save action to keep HubSpot contacts up to date when members change their details.

---

## Email Queuing During Bulk Import

Sending 107 set-password emails in one go when the import runs would cause several problems:

- **Server load** — PHP mailer opens a new SMTP connection for every email in quick succession.
- **Spam/deliverability risk** — sending a large spike of emails from one domain in seconds can trigger spam filters at receiving mail servers and damage the domain's sending reputation.
- **Email failures going unnoticed** — if one email fails mid-batch the rest may not send, with no clear record of which ones went through.

### Solution — WordPress Cron Email Queue

Instead of sending emails immediately during import, the import process will:

1. **Create an email queue** — each user that needs a set-password email is added to a queue stored in the database (`csc_email_queue` option or a simple custom DB table). Each queue entry holds: user ID, email address, subject, body, and a `sent` flag.
2. **Schedule a WP Cron job** — immediately after the import finishes, a recurring WP Cron event is scheduled to run **every 2 minutes**.
3. **Each cron run sends a small batch** — the cron handler picks the next **5 unsent emails** from the queue, sends them, marks them as sent, and stops. At that rate 107 emails would be fully delivered in roughly 45 minutes, which is gentle enough not to trip spam filters.
4. **The queue is self-clearing** — once all emails in the queue are sent, the cron event unschedules itself automatically.

### Admin visibility

The Import results page will show:
- How many emails are queued vs. sent in real time (auto-refreshes).
- A **"Pause queue"** toggle so the admin can hold sending if they want to review first.
- A **"Send all now"** override button if they decide they want immediate delivery (e.g. for a small test import of 5 users).
- Any failed sends (with the error reason) listed separately so they can be retried individually.

### Configurable batch size

The Integrations settings page will include a field for **"Emails per batch"** (default 5) and **"Minutes between batches"** (default 2). This lets the admin tune the rate based on the hosting environment and the mail server's limits.

### Why not a third-party queue plugin?

We are keeping this within the plugin to avoid adding a dependency. WordPress's built-in WP Cron is sufficient for this volume and the queue logic is straightforward. The same queuing mechanism will also be reused for HubSpot API calls during bulk import (see below), so it is worth building properly.

### HubSpot sync queue during import

The same cron queue approach applies to HubSpot API calls during bulk import. HubSpot's API has rate limits (100 requests per 10 seconds on most plans). Rather than hammering the API during the import, HubSpot sync jobs are queued and processed at a safe rate (e.g. 20 contacts per cron run, every 2 minutes).

---

## Confirmed Decisions

The following questions have been answered and are locked in for implementation:

| Question | Decision |
|---|---|
| HubSpot access | Holly has HubSpot login. A new **Private App** will be created in HubSpot (Settings → Integrations → Private Apps) and the token pasted into the portal's Integrations settings page. |
| File format for import | **CSV only** — no Excel (.xlsx) support needed. |

---

## Open Questions Still to Confirm

2. **Custom HubSpot properties** — should we create new custom properties in HubSpot (`csc_member_status`, `csc_portal_user_id` etc.) or map to fields that already exist in your HubSpot account?

3. **Import status for the 107 contacts** — should all be imported as `approved` immediately (they have already applied via the survey), or should some go in as `pending` for manual review?

4. **Company admin assignment** — for the 90 companies, the first person listed in the CSV for that company will automatically become the company admin. Is that acceptable, or do you need to specify a particular person per company?

5. **Password email timing** — now that we have a queue, the default plan is to drip the 107 set-password emails over roughly 45 minutes after the import completes. Is that acceptable, or would you prefer to hold all emails and trigger them manually at a specific time?
