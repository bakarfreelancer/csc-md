<?php
/**
 * Bulk Import admin page — Upload CSV (using sample template), preview, batched AJAX import.
 * Column names in the CSV must match the sample template exactly — no manual mapping required.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/admin
 */
class Csc_Import {

	// All recognised field names (must match sample CSV headers exactly)
	const KNOWN_FIELDS = array(
		'first_name', 'last_name', 'email', 'phone', 'job_title', 'org_name',
		'consent_directory', 'consent_sharing', 'consent_marketing',
		'is_company_admin', 'sync_to_hubspot', 'send_email', 'status',
	);

	public function register_hooks( $loader ) {
		$loader->add_action( 'admin_menu',                    $this, 'add_admin_menu' );
		$loader->add_action( 'wp_ajax_csc_import_upload',     $this, 'ajax_upload' );
		$loader->add_action( 'wp_ajax_csc_import_run_batch',  $this, 'ajax_run_batch' );
		$loader->add_action( 'wp_ajax_csc_import_sample_csv', $this, 'ajax_sample_csv' );
	}

	/* -----------------------------------------------------------------------
	 * Menu
	 * --------------------------------------------------------------------- */

	public function add_admin_menu() {
		add_submenu_page(
			'csc-members',
			'Import Users / Companies',
			'Import Users',
			'manage_options',
			'csc-import',
			array( $this, 'render_page' )
		);
	}

	/* -----------------------------------------------------------------------
	 * Page render
	 * --------------------------------------------------------------------- */

	public function render_page() {
		$nonce = wp_create_nonce( 'csc_import_nonce' );
		?>
		<div class="wrap" id="csc-import-wrap">
			<h1>Import Users / Companies</h1>

			<!-- Step 1: Upload -->
			<div class="csc-import-step card" id="csc-step-upload" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
				<h2 style="margin-top:0;">Step 1 — Upload CSV</h2>
				<p>Use the sample template below as your CSV file. Column names must match exactly — the import will automatically recognise all fields.</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=csc_import_sample_csv&nonce=' . wp_create_nonce( 'csc_import_nonce' ) ) ); ?>"
						class="button" download="csc-import-sample.csv">
						&#8615; Download Sample CSV Template
					</a>
				</p>
				<p style="margin-top:16px;">
					<input type="file" id="csc-csv-file" accept=".csv" />
					<button type="button" class="button button-primary" id="csc-upload-btn" style="margin-left:8px;">Upload &amp; Preview</button>
				</p>
				<p id="csc-upload-result" style="color:red;display:none;"></p>
			</div>

			<!-- Step 2: Options + Preview -->
			<div class="csc-import-step" id="csc-step-preview" style="display:none;">
				<div class="card" style="max-width:900px;padding:20px 24px;margin-bottom:16px;">
					<h2 style="margin-top:0;">Step 2 — Import Options</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th>Default status</th>
							<td>
								<select id="opt-status">
									<option value="approved">Approved</option>
									<option value="pending">Pending</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Send set-password email</th>
							<td><label><input type="checkbox" id="opt-send-email" checked /> Yes — queue emails after import</label></td>
						</tr>
						<tr>
							<th>Mark first user per company as company admin</th>
							<td><label><input type="checkbox" id="opt-company-admin" checked /> Yes</label></td>
						</tr>
						<tr>
							<th>Sync to HubSpot</th>
							<td>
								<label><input type="checkbox" id="opt-hubspot" checked /> Yes — push new contacts to HubSpot</label>
								<p class="description">Uncheck only if these contacts already exist in HubSpot (e.g. this CSV came from HubSpot).</p>
							</td>
						</tr>
						<tr>
							<th>Skip existing users</th>
							<td><label><input type="checkbox" id="opt-skip-existing" checked /> Yes — skip rows where the email already exists</label></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
					<h2 style="margin-top:0;">Preview <span id="csc-preview-count" style="font-weight:400;font-size:14px;color:#6b7280;"></span></h2>
					<div id="csc-preview-table-wrap"></div>
				</div>

				<div style="margin-bottom:24px;">
					<button type="button" class="button button-primary button-hero" id="csc-confirm-import-btn">Confirm Import</button>
					<button type="button" class="button" id="csc-back-btn" style="margin-left:12px;">← Back</button>
				</div>
			</div>

			<!-- Step 3: Progress + Results -->
			<div class="csc-import-step" id="csc-step-results" style="display:none;">
				<div class="card" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
					<h2 style="margin-top:0;" id="csc-import-heading">Importing…</h2>
					<div id="csc-progress-wrap" style="margin-bottom:16px;">
						<div style="background:#e5e7eb;border-radius:4px;height:20px;overflow:hidden;">
							<div id="csc-progress-bar" style="background:#2563eb;height:100%;width:0%;transition:width 0.3s;"></div>
						</div>
						<p id="csc-progress-label" style="margin-top:6px;">0 of 0 rows processed</p>
					</div>
					<div id="csc-result-summary" style="display:none;margin-bottom:12px;"></div>
					<div id="csc-result-table-wrap" style="display:none;">
						<h3>Row Results</h3>
						<table class="wp-list-table widefat fixed striped">
							<thead><tr><th>#</th><th>Email</th><th>Company</th><th>Result</th><th>HubSpot</th></tr></thead>
							<tbody id="csc-result-rows"></tbody>
						</table>
						<p style="margin-top:12px;">
							<button type="button" class="button" id="csc-download-log">&#8615; Download Full Log (CSV)</button>
						</p>
					</div>
				</div>

				<!-- Email queue status -->
				<div class="card" id="csc-eq-section" style="max-width:800px;padding:20px 24px;display:none;">
					<h2 style="margin-top:0;">Email Queue</h2>
					<div id="csc-eq-stats-import"></div>
					<p>
						<button type="button" class="button button-primary" id="csc-eq-send-all-import">Send All Now</button>
						<button type="button" class="button" id="csc-eq-pause-import" style="margin-left:8px;">Pause Queue</button>
					</p>
				</div>
			</div>
		</div>

		<script>
		jQuery(function($){

			var nonce      = '<?php echo esc_js( $nonce ); ?>';
			var eqNonce    = '<?php echo esc_js( wp_create_nonce( 'csc_integrations_nonce' ) ); ?>';
			var previewRows = [];
			var importLog   = [];

			function ajax(action, data, cb) {
				data = $.extend({ action: action, nonce: nonce }, data);
				$.post(ajaxurl, data).done(cb).fail(function(){
					cb({ success: false, data: { message: 'Network error.' } });
				});
			}

			/* ----- Step 1: Upload ----- */
			$('#csc-upload-btn').on('click', function(){
				var file = $('#csc-csv-file')[0].files[0];
				if (!file) { alert('Please select a CSV file.'); return; }

				var fd = new FormData();
				fd.append('action', 'csc_import_upload');
				fd.append('nonce', nonce);
				fd.append('csv_file', file);

				var $btn = $(this).prop('disabled', true).text('Uploading…');
				$('#csc-upload-result').hide();

				$.ajax({ url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false })
					.done(function(res){
						$btn.prop('disabled', false).text('Upload & Preview');
						if (!res.success) {
							$('#csc-upload-result').text(res.data ? res.data.message : 'Upload failed.').show();
							return;
						}
						previewRows = res.data.rows;
						renderPreviewTable(previewRows);
						$('#csc-step-upload').hide();
						$('#csc-step-preview').show();
					})
					.fail(function(){
						$btn.prop('disabled', false).text('Upload & Preview');
						$('#csc-upload-result').text('Network error.').show();
					});
			});

			/* ----- Step 2: Preview ----- */
			function renderPreviewTable(rows) {
				var total   = rows.length;
				var creates = rows.filter(function(r){ return r.action_type === 'create'; }).length;
				var skips   = total - creates;
				$('#csc-preview-count').text('(' + total + ' rows: ' + creates + ' to create, ' + skips + ' to skip)');

				var html = '<table class="wp-list-table widefat fixed striped" style="font-size:13px;">';
				html += '<thead><tr><th style="width:30px;">#</th><th>Name</th><th>Email</th><th>Company</th><th>Job Title</th><th>Action</th></tr></thead><tbody>';
				rows.forEach(function(r, i){
					var actionColor = r.action_type === 'create' ? '#16a34a' : '#6b7280';
					html += '<tr>';
					html += '<td>' + (i+1) + '</td>';
					html += '<td>' + esc(r.first_name) + ' ' + esc(r.last_name) + '</td>';
					html += '<td>' + esc(r.email) + (r.user_exists ? ' <span style="color:#d97706;" title="User already exists in portal">⚠</span>' : '') + '</td>';
					html += '<td>' + esc(r.org_name) + (r.org_exists ? ' <span style="color:#2563eb;" title="Company already exists — user will be linked">&#x1F517;</span>' : '') + '</td>';
					html += '<td>' + esc(r.job_title) + '</td>';
					html += '<td style="color:' + actionColor + ';font-weight:600;">' + esc(r.action) + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				html += '<p style="margin-top:8px;color:#6b7280;font-size:12px;">⚠ user already exists &nbsp;&#x1F517; will link to existing company</p>';
				$('#csc-preview-table-wrap').html(html);
			}

			function esc(s) {
				return $('<span>').text(s || '').html();
			}

			$('#csc-back-btn').on('click', function(){
				$('#csc-step-preview').hide();
				$('#csc-step-upload').show();
			});

			/* ----- Step 3: Import ----- */
			$('#csc-confirm-import-btn').on('click', function(){
				$('#csc-step-preview').hide();
				$('#csc-step-results').show();
				importLog = [];
				$('#csc-result-rows').empty();
				$('#csc-result-table-wrap').hide();
				$('#csc-result-summary').hide().empty();
				$('#csc-import-heading').text('Importing…');

				var options = {
					status:        $('#opt-status').val(),
					send_email:    $('#opt-send-email').is(':checked') ? '1' : '0',
					company_admin: $('#opt-company-admin').is(':checked') ? '1' : '0',
					sync_hubspot:  $('#opt-hubspot').is(':checked') ? '1' : '0',
					skip_existing: $('#opt-skip-existing').is(':checked') ? '1' : '0',
				};

				var total    = previewRows.length;
				var batchSz  = 20;
				var offset   = 0;
				var seenOrgs = {};

				updateProgress(0, total);

				function runBatch() {
					var batch = previewRows.slice(offset, offset + batchSz);
					if (batch.length === 0) {
						finishImport(total);
						return;
					}
					ajax('csc_import_run_batch', {
						rows:      JSON.stringify(batch),
						options:   JSON.stringify(options),
						seen_orgs: JSON.stringify(seenOrgs),
					}, function(res){
						if (res.success) {
							res.data.results.forEach(function(r){
								importLog.push(r);
								appendResultRow(r);
							});
							if (res.data.seen_orgs) {
								seenOrgs = res.data.seen_orgs;
							}
						}
						offset += batchSz;
						updateProgress(Math.min(offset, total), total);
						runBatch();
					});
				}

				runBatch();
			});

			function updateProgress(done, total) {
				var pct = total > 0 ? Math.round((done / total) * 100) : 0;
				$('#csc-progress-bar').css('width', pct + '%');
				$('#csc-progress-label').text(done + ' of ' + total + ' rows processed');
			}

			function appendResultRow(r) {
				var color = r.action === 'Created' ? '#16a34a' : (r.action === 'Skipped' ? '#6b7280' : (r.action === 'Updated' ? '#2563eb' : '#dc2626'));
				var hsColor = !r.hs ? '#9ca3af' : (r.hs === 'Synced' ? '#16a34a' : '#dc2626');
				$('#csc-result-rows').append(
					$('<tr>').append(
						$('<td>').text(importLog.length),
						$('<td>').text(r.email || ''),
						$('<td>').text(r.org_name || ''),
						$('<td>').css({ color: color, fontWeight: '600' }).text(r.action + (r.error ? ': ' + r.error : '')),
						$('<td>').css({ color: hsColor, fontWeight: r.hs ? '600' : '400' }).text(r.hs || '—')
					)
				);
			}

			function finishImport(total) {
				$('#csc-import-heading').text('Import complete');
				$('#csc-progress-bar').css({ width: '100%', background: '#16a34a' });
				$('#csc-progress-label').text('Done — ' + total + ' rows processed.');

				var counts = { Created: 0, Skipped: 0, Updated: 0, Error: 0 };
				importLog.forEach(function(r){
					var key = (r.action === 'Created' || r.action === 'Skipped' || r.action === 'Updated') ? r.action : 'Error';
					counts[key]++;
				});
				$('#csc-result-summary').html(
					'<p><strong>Summary:</strong> ' + counts.Created + ' created, ' + counts.Skipped + ' skipped, ' + counts.Updated + ' updated, ' + counts.Error + ' errors.</p>'
				).show();
				$('#csc-result-table-wrap').show();

				refreshEqStats();
				$('#csc-eq-section').show();
			}

			/* ----- Download log ----- */
			$('#csc-download-log').on('click', function(){
				var csv = 'Email,Company,Action,HubSpot\n';
				importLog.forEach(function(r){
					csv += '"' + (r.email||'').replace(/"/g,'""') + '","' + (r.org_name||'').replace(/"/g,'""') + '","' + (r.action||'').replace(/"/g,'""') + '","' + (r.hs||'').replace(/"/g,'""') + '"\n';
				});
				var blob = new Blob([csv], { type: 'text/csv' });
				var a    = document.createElement('a');
				a.href   = URL.createObjectURL(blob);
				a.download = 'import-log.csv';
				a.click();
			});

			/* ----- Email queue controls ----- */
			function refreshEqStats() {
				$.post(ajaxurl, { action: 'csc_eq_stats', nonce: eqNonce }).done(function(res){
					if (res.success) $('#csc-eq-stats-import').html(res.data.html);
				});
			}

			$('#csc-eq-send-all-import').on('click', function(){
				if (!confirm('Send all queued emails now?')) return;
				$(this).prop('disabled', true);
				$.post(ajaxurl, { action: 'csc_eq_send_all', nonce: eqNonce }).always(function(){
					$('#csc-eq-send-all-import').prop('disabled', false);
					refreshEqStats();
				});
			});

			$('#csc-eq-pause-import').on('click', function(){
				var paused = $(this).data('paused');
				$.post(ajaxurl, { action: paused ? 'csc_eq_resume' : 'csc_eq_pause', nonce: eqNonce }).always(function(){
					refreshEqStats();
					$('#csc-eq-pause-import').data('paused', paused ? 0 : 1).text(paused ? 'Pause Queue' : 'Resume Queue');
				});
			});

		});
		</script>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Upload CSV — parse, auto-map, return preview rows
	 * --------------------------------------------------------------------- */

	public function ajax_upload() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		if ( empty( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( array( 'message' => 'No file received.' ) );
		}

		$file = $_FILES['csv_file'];
		$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( $ext !== 'csv' ) {
			wp_send_json_error( array( 'message' => 'Only CSV files are supported.' ) );
		}

		$raw_rows = $this->parse_csv( $file['tmp_name'] );
		if ( empty( $raw_rows ) ) {
			wp_send_json_error( array( 'message' => 'The CSV appears to be empty or malformed.' ) );
		}

		// Validate that 'email' column exists
		$headers = array_keys( $raw_rows[0] );
		if ( ! in_array( 'email', array_map( 'strtolower', $headers ), true ) ) {
			wp_send_json_error( array( 'message' => 'The CSV must contain an "email" column. Please use the sample template.' ) );
		}

		// Auto-map: keep only recognised field names, ignore unknown columns
		$preview = array();
		foreach ( $raw_rows as $row ) {
			$mapped = array();
			foreach ( self::KNOWN_FIELDS as $field ) {
				$mapped[ $field ] = $row[ $field ] ?? '';
			}

			$email       = strtolower( trim( $mapped['email'] ) );
			$org_name    = trim( $mapped['org_name'] );
			$user_exists = $email && email_exists( $email );
			$org_exists  = $org_name && $this->find_org( $org_name );

			if ( ! $email ) {
				$action_type = 'skip';
				$action      = 'Skip (no email)';
			} elseif ( $user_exists ) {
				$action_type = 'skip';
				$action      = 'Skip (user exists)';
			} else {
				$action_type = 'create';
				$action      = 'Create' . ( $org_exists ? ' + link to existing company' : ' + create company' );
			}

			$preview[] = array_merge( $mapped, array(
				'user_exists' => (bool) $user_exists,
				'org_exists'  => (bool) $org_exists,
				'action'      => $action,
				'action_type' => $action_type,
			) );
		}

		wp_send_json_success( array( 'rows' => $preview ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Run a batch of import rows
	 * --------------------------------------------------------------------- */

	public function ajax_run_batch() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$rows      = json_decode( wp_unslash( $_POST['rows'] ?? '' ), true );
		$opts      = json_decode( wp_unslash( $_POST['options'] ?? '' ), true );
		$seen_orgs = json_decode( wp_unslash( $_POST['seen_orgs'] ?? '{}' ), true ) ?: array();

		if ( ! is_array( $rows ) || ! is_array( $opts ) ) {
			wp_send_json_error( array( 'message' => 'Invalid batch data.' ) );
		}

		$hs      = new Csc_Hubspot();
		$eq      = new Csc_Email_Queue();
		$results = array();

		$ctx = array(
			'default_status' => in_array( $opts['status'] ?? '', array( 'approved', 'pending' ), true ) ? $opts['status'] : 'approved',
			'send_email'     => ( $opts['send_email'] ?? '0' ) === '1',
			'company_admin'  => ( $opts['company_admin'] ?? '1' ) === '1',
			'sync_hubspot'   => ( $opts['sync_hubspot'] ?? '0' ) === '1',
			'skip_existing'  => ( $opts['skip_existing'] ?? '1' ) === '1',
			'seen_orgs'      => &$seen_orgs,
			'hs'             => $hs,
			'eq'             => $eq,
		);

		foreach ( $rows as $row ) {
			$results[] = $this->import_row( $row, $ctx );
		}

		wp_send_json_success( array(
			'results'   => $results,
			'seen_orgs' => $seen_orgs,
		) );
	}

	/* -----------------------------------------------------------------------
	 * Core: import a single row
	 * --------------------------------------------------------------------- */

	private function import_row( $row, &$ctx ) {
		$email     = strtolower( trim( $row['email'] ?? '' ) );
		$first     = sanitize_text_field( $row['first_name'] ?? '' );
		$last      = sanitize_text_field( $row['last_name'] ?? '' );
		$phone     = sanitize_text_field( $row['phone'] ?? '' );
		$org_name  = sanitize_text_field( $row['org_name'] ?? '' );
		$job_title = sanitize_text_field( $row['job_title'] ?? '' );

		// Use global import options — per-row CSV values are intentionally ignored
		// to prevent CSV column values from silently overriding the admin's selection.
		$row_status        = in_array( $row['status'] ?? '', array( 'approved', 'pending' ), true ) ? $row['status'] : $ctx['default_status'];
		$row_send_email    = $ctx['send_email'];
		$row_company_admin = $ctx['company_admin'];
		$row_sync_hubspot  = $ctx['sync_hubspot'];

		$log = array( 'email' => $email, 'org_name' => $org_name, 'action' => '', 'error' => '', 'hs' => '' );

		if ( ! $email || ! is_email( $email ) ) {
			$log['action'] = 'Skip';
			$log['error']  = 'Invalid or missing email';
			return $log;
		}

		$token = get_option( 'csc_hubspot_token', '' );

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			if ( $ctx['skip_existing'] ) {
				if ( $row_sync_hubspot ) {
					$log['hs'] = $this->do_hs_sync( $existing_user->ID, 0, false, $ctx['hs'], $token );
				}
				$log['action'] = 'Skipped';
				return $log;
			}
			$this->save_user_meta( $existing_user->ID, $row, $job_title, $phone );
			if ( $row_sync_hubspot ) {
				$log['hs'] = $this->do_hs_sync( $existing_user->ID, 0, false, $ctx['hs'], $token );
			}
			$log['action'] = 'Updated';
			return $log;
		}

		// Create path
		$org_already_existed = $org_name && (bool) $this->find_org( $org_name );
		$org_id              = $org_name ? $this->find_or_create_org( $org_name ) : 0;
		$user_id = wp_insert_user( array(
			'user_login'   => $email,
			'user_email'   => $email,
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( "$first $last" ),
			'user_pass'    => wp_generate_password( 24 ),
			'role'         => 'subscriber',
		) );

		if ( is_wp_error( $user_id ) ) {
			$log['action'] = 'Error';
			$log['error']  = $user_id->get_error_message();
			return $log;
		}

		update_user_meta( $user_id, '_csc_status',          $row_status );
		update_user_meta( $user_id, '_csc_organisation_id', $org_id );
		update_user_meta( $user_id, '_csc_2fa_enabled',     '0' );
		update_user_meta( $user_id, '_csc_login_alerts',    '0' );
		$this->save_user_meta( $user_id, $row, $job_title, $phone );

		// Company admin — only granted for newly created companies, never for existing ones
		if ( $row_company_admin && $org_name && ! $org_already_existed && empty( $ctx['seen_orgs'][ $org_name ] ) ) {
			update_user_meta( $user_id, '_csc_can_edit_company', '1' );
			$ctx['seen_orgs'][ $org_name ] = true;
		} else {
			update_user_meta( $user_id, '_csc_can_edit_company', '0' );
		}

		if ( $row_send_email ) {
			$this->queue_approval_email( $user_id, $ctx['eq'] );
		}

		if ( $row_sync_hubspot ) {
			$log['hs'] = $this->do_hs_sync( $user_id, $org_id, ! $org_already_existed, $ctx['hs'], $token );
		}

		$log['action'] = 'Created';
		return $log;
	}

	/**
	 * Run HubSpot sync for a user and optionally their company.
	 * Returns a short status string for display in the import results table.
	 *
	 * @param int        $user_id
	 * @param int        $org_id            0 = skip company sync
	 * @param bool       $sync_company      true = also sync the company record
	 * @param Csc_Hubspot $hs
	 * @param string     $token
	 * @return string
	 */
	private function do_hs_sync( $user_id, $org_id, $sync_company, $hs, $token ) {
		if ( ! $token ) {
			return 'No token';
		}

		if ( $org_id && $sync_company ) {
			$hs->sync_company( $org_id );
		}

		$result = $hs->sync_contact( $user_id );
		if ( is_wp_error( $result ) ) {
			return 'HS error: ' . $result->get_error_message();
		}

		return 'Synced';
	}

	private function save_user_meta( $user_id, $row, $job_title, $phone ) {
		if ( $phone )     update_user_meta( $user_id, '_csc_phone',     $phone );
		if ( $job_title ) update_user_meta( $user_id, '_csc_job_title', $job_title );

		$bool = function( $v ) { return ( $v === '1' || strtolower( $v ) === 'yes' ) ? '1' : '0'; };

		$consent_dir = $bool( $row['consent_directory'] ?? '' );
		$consent_shr = $bool( $row['consent_sharing']   ?? '' );
		$consent_mkt = $bool( $row['consent_marketing'] ?? '' );

		update_user_meta( $user_id, '_csc_consent_directory',  $consent_dir );
		update_user_meta( $user_id, '_csc_dir_org_visible',    $consent_dir );
		update_user_meta( $user_id, '_csc_dir_profile_visible', $consent_dir );
		update_user_meta( $user_id, '_csc_consent_sharing',    $consent_shr );
		update_user_meta( $user_id, '_csc_consent_marketing',  $consent_mkt );
		update_user_meta( $user_id, '_csc_notif_newsletter',   $consent_mkt );
	}

	private function queue_approval_email( $user_id, $eq ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) return;

		$key        = get_password_reset_key( $user );
		$setpw_page = get_page_by_path( 'members-set-password' );
		$reset_url  = add_query_arg( array(
			'key'   => $key,
			'login' => rawurlencode( $user->user_login ),
		), $setpw_page ? get_permalink( $setpw_page->ID ) : home_url( '/members-set-password/' ) );

		$login_page = get_page_by_path( 'members-login' );
		$login_url  = $login_page ? get_permalink( $login_page->ID ) : wp_login_url();

		$subject = 'Your Celtic Sea Cluster Membership Has Been Approved';
		$body    = 'Dear ' . $user->first_name . ",\n\n"
			. "We are pleased to confirm that your application to join the Celtic Sea Cluster has been approved.\n\n"
			. "You can now set your password and access the Members Portal using the link below:\n"
			. $reset_url . "\n\n"
			. "Once your password has been created, you will be able to log in here:\n"
			. $login_url . "\n\n"
			. "Within the portal, you can create and manage your member profile, access the Member Directory, connect with other members through the forum, and view the latest newsletters and resources.\n\n"
			. "Welcome to the Celtic Sea Cluster. We are delighted to have you as part of the network.\n\n"
			. "Kind regards,\n\nThe Celtic Sea Cluster Team\n";

		$eq->enqueue( $user_id, $user->user_email, $subject, $body );
	}

	/* -----------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------- */

	private function parse_csv( $file_path ) {
		$rows   = array();
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) return $rows;

		// Strip BOM if present
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		$headers = fgetcsv( $handle );
		if ( ! $headers ) { fclose( $handle ); return $rows; }
		$headers = array_map( 'trim', $headers );

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			$data   = array_slice( array_pad( $data, count( $headers ), '' ), 0, count( $headers ) );
			$rows[] = array_combine( $headers, array_map( 'trim', $data ) );
		}

		fclose( $handle );
		return $rows;
	}

	private function find_org( $name ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'csc_organisation'
			   AND post_status = 'publish'
			   AND LOWER(post_title) = LOWER(%s)
			 LIMIT 1",
			$name
		) );
		return $id ? intval( $id ) : null;
	}

	private function find_or_create_org( $name ) {
		$existing = $this->find_org( $name );
		if ( $existing ) return $existing;

		return wp_insert_post( array(
			'post_type'   => 'csc_organisation',
			'post_title'  => $name,
			'post_status' => 'publish',
		) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Download sample CSV template
	 * --------------------------------------------------------------------- */

	public function ajax_sample_csv() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.', 403 );
		}

		$columns = self::KNOWN_FIELDS;

		$sample_rows = array(
			array( 'Jane', 'Smith', 'jane.smith@example.com', '+44 7700 900001', 'Head of Offshore', 'Acme Energy Ltd', '1', '1', '1', '', '', '', '' ),
			array( 'John', 'Doe',   'john.doe@example.com',   '+44 7700 900002', 'Marine Engineer',  'Acme Energy Ltd', '1', '0', '0', '0', '', '', 'approved' ),
			array( 'Alice', 'Murphy', 'alice.murphy@another.org', '', 'Director', 'Blue Ocean Solutions', '1', '1', '1', '1', '1', '1', '' ),
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="csc-import-sample.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $columns );
		foreach ( $sample_rows as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}
}
