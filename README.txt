=== CSC Member Directory ===
Contributors: abubakar
Tags: members, directory, registration, login
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Custom plugin for the Celtic Sea Cluster (CSC) Members Portal.

== Description ==

Provides the complete Members Portal for the Celtic Sea Cluster website, including:

* Branded login page
* Multi-step member registration / join flow
* Organisation management (custom post type)
* Admin panel for approving / rejecting member applications
* Member Dashboard (in progress)
* Member Directory — Companies & Representatives (in progress)
* Forum, Newsletters, Settings (in progress)

== Installation ==

1. Activate the plugin via the Plugins menu in WordPress.
2. On activation, three pages are automatically created:
   - /members-login/     — Login page  ([csc_login] shortcode)
   - /join-csc/          — Join page   ([csc_join] shortcode)
   - /member-dashboard/  — Dashboard   ([csc_dashboard] shortcode, coming soon)
3. Go to CSC Members in the admin menu to approve pending applications.

== Shortcodes ==

[csc_login]
  Renders the CSC-branded login form.
  - Logged-in pending users see "Application Under Review" message.
  - Logged-in approved users are redirected to /member-dashboard/.

[csc_join]
  Renders the multi-step Join / Registration form.
  Step 1: Select existing organisation (typeahead) + personal data.
  Step 2 (new org flow): Register organisation → then personal data.
  On submit: creates a WordPress user with status=pending and notifies admin.

== Admin Panel ==

Navigate to CSC Members in the WordPress admin sidebar.
- Pending tab: Review new applications. Click Approve or Reject.
  - Approve sends the member an email with a password-reset link.
  - Reject sets their status to rejected (can be reversed later).
- Approved tab: View active members. Revoke Access if needed.
- Rejected tab: View rejected applications. Can be re-approved.

A "CSC Status" column is also added to the standard Users list.

== Data Model ==

Organisation (post type: csc_organisation)
  - Post title = Organisation name
  - _csc_org_location, _csc_org_sector, _csc_org_industry
  - _csc_org_igp_category, _csc_org_country, _csc_org_county, _csc_org_postcode

User meta
  - _csc_status          : pending | approved | rejected
  - _csc_job_title       : job title entered at registration
  - _csc_organisation_id : post ID of linked csc_organisation

== Colors ==

Blue  : #1F2D57
Green : #44BD70

== Changelog ==

= 1.0.0 =
* Initial release — Login & Registration (Step 1)
