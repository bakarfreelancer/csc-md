<?php
/**
 * Bulk Import admin page — Upload CSV, map columns, preview, batched AJAX import.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/admin
 */
class Csc_Import {

	public function register_hooks( $loader ) {
		$loader->add_action( 'admin_menu',                        $this, 'add_admin_menu' );
		$loader->add_action( 'wp_ajax_csc_import_upload',         $this, 'ajax_upload' );
		$loader->add_action( 'wp_ajax_csc_import_save_mapping',   $this, 'ajax_save_mapping' );
		$loader->add_action( 'wp_ajax_csc_import_preview',        $this, 'ajax_preview' );
		$loader->add_action( 'wp_ajax_csc_import_run_batch',      $this, 'ajax_run_batch' );
		$loader->add_action( 'wp_ajax_csc_import_download_log',   $this, 'ajax_download_log' );
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
		$nonce          = wp_create_nonce( 'csc_import_nonce' );
		$saved_mapping  = get_option( 'csc_import_column_mapping', array() );
		$mapping_json   = wp_json_encode( $saved_mapping );
		?>
		<div class="wrap" id="csc-import-wrap">
			<h1>Import Users / Companies</h1>

			<!-- Step 1: Upload -->
			<div class="csc-import-step card" id="csc-step-upload" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
				<h2 style="margin-top:0;">Step 1 — Upload CSV</h2>
				<p>Upload a CSV file. After uploading you will be able to map each column to a portal field before previewing the import.</p>
				<input type="file" id="csc-csv-file" accept=".csv" />
				<button type="button" class="button button-primary" id="csc-upload-btn" style="margin-left:8px;">Upload &amp; Map Columns</button>
				<span id="csc-upload-result" style="margin-left:8px;"></span>
			</div>

			<!-- Step 2: Column Mapping -->
			<div class="csc-import-step card" id="csc-step-mapping" style="max-width:800px;padding:20px 24px;margin-bottom:24px;display:none;">
				<h2 style="margin-top:0;">Step 2 — Map Columns</h2>
				<p>Match each column from your CSV to the corresponding portal field. Saved mappings from previous imports are pre-filled.</p>
				<table class="wp-list-table widefat fixed" id="csc-mapping-table">
					<thead><tr><th>CSV Column</th><th>Maps to</th></tr></thead>
					<tbody></tbody>
				</table>
				<p style="margin-top:12px;">
					<button type="button" class="button button-primary" id="csc-preview-btn">Preview Import</button>
					<button type="button" class="button" id="csc-save-mapping-btn" style="margin-left:8px;">Save Mapping for Next Time</button>
					<span id="csc-mapping-result" style="margin-left:8px;"></span>
				</p>
			</div>

			<!-- Step 3: Options + Preview -->
			<div class="csc-import-step" id="csc-step-preview" style="display:none;">
				<div class="card" style="max-width:900px;padding:20px 24px;margin-bottom:16px;">
					<h2 style="margin-top:0;">Step 3 — Import Options</h2>
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
								<label><input type="checkbox" id="opt-hubspot" /> Yes — push new contacts to HubSpot</label>
								<p class="description">Leave unchecked if these contacts already exist in HubSpot (e.g. this CSV came from HubSpot).</p>
							</td>
						</tr>
						<tr>
							<th>Skip existing users</th>
							<td><label><input type="checkbox" id="opt-skip-existing" checked /> Yes — skip rows where the email already exists</label></td>
						</tr>
					</table>
				</div>

				<div class="card" style="max-width:100%;padding:20px 24px;margin-bottom:16px;">
					<h2 style="margin-top:0;">Preview</h2>
					<div id="csc-preview-table-wrap"></div>
				</div>

				<div style="margin-bottom:24px;">
					<button type="button" class="button button-primary button-hero" id="csc-confirm-import-btn">Confirm Import</button>
					<button type="button" class="button" id="csc-back-btn" style="margin-left:12px;">← Back to Mapping</button>
				</div>
			</div>

			<!-- Step 4: Progress + Results -->
			<div class="csc-import-step" id="csc-step-results" style="display:none;">
				<div class="card" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
					<h2 style="margin-top:0;">Importing…</h2>
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
							<thead><tr><th>#</th><th>Email</th><th>Company</th><th>Result</th></tr></thead>
							<tbody id="csc-result-rows"></tbody>
						</table>
						<p style="margin-top:12px;">
							<button type="button" class="button" id="csc-download-log">Download Full Log (CSV)</button>
						</p>
					</div>
				</div>

				<!-- Email queue status (shown after import with emails queued) -->
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

			var nonce       = '<?php echo esc_js( $nonce ); ?>';
			var savedMapping = <?php echo $mapping_json; ?> || {};

			// Portal field options
			var portalFields = [
				{ value: '',                    label: '— Ignore —' },
				{ value: 'first_name',          label: 'First Name' },
				{ value: 'last_name',           label: 'Last Name' },
				{ value: 'email',               label: 'Email (required)' },
				{ value: 'phone',               label: 'Phone' },
				{ value: 'org_name',            label: 'Organisation Name' },
				{ value: 'job_title',           label: 'Job Title' },
				{ value: 'consent_directory',   label: 'Directory Consent' },
				{ value: 'consent_sharing',     label: 'Sharing Consent' },
				{ value: 'consent_marketing',   label: 'Marketing/Newsletter Consent' },
				{ value: 'is_company_admin',    label: 'Company Admin (1/0)' },
				{ value: 'sync_to_hubspot',     label: 'Sync to HubSpot (1/0)' },
				{ value: 'send_email',          label: 'Send Email (1/0)' },
				{ value: 'status',              label: 'Status (approved/pending)' },
			];

			var csvHeaders = [];
			var previewRows = [];
			var importLog = [];

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
				$(this).prop('disabled', true).text('Uploading…');
				var $btn = $(this);
				$.ajax({ url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false })
					.done(function(res){
						$btn.prop('disabled', false).text('Upload & Map Columns');
						if (!res.success) {
							$('#csc-upload-result').css('color','red').text(res.data ? res.data.message : 'Upload failed.');
							return;
						}
						csvHeaders = res.data.headers;
						buildMappingTable();
						$('#csc-step-upload').hide();
						$('#csc-step-mapping').show();
					}).fail(function(){
						$btn.prop('disabled', false).text('Upload & Map Columns');
						$('#csc-upload-result').css('color','red').text('Network error.');
					});
			});

			/* ----- Step 2: Mapping ----- */
			function buildMappingTable() {
				var $tbody = $('#csc-mapping-table tbody').empty();
				csvHeaders.forEach(function(col){
					var sel = $('<select class="csc-field-map">');
					portalFields.forEach(function(f){
						var $opt = $('<option>').val(f.value).text(f.label);
						// Auto-select from saved mapping
						if (savedMapping[col] && savedMapping[col] === f.value) {
							$opt.prop('selected', true);
						}
						sel.append($opt);
					});
					// Auto-map common names if no saved mapping
					if (!savedMapping[col]) {
						var lc = col.toLowerCase();
						if (lc.indexOf('first') !== -1 && lc.indexOf('name') !== -1) sel.val('first_name');
						else if (lc.indexOf('last') !== -1 && lc.indexOf('name') !== -1) sel.val('last_name');
						else if (lc === 'email' || lc === 'e-mail') sel.val('email');
						else if (lc.indexOf('phone') !== -1) sel.val('phone');
						else if (lc === 'company' || lc.indexOf('organisation') !== -1) sel.val('org_name');
						else if (lc.indexOf('job title') !== -1 || lc.indexOf('jobtitle') !== -1) sel.val('job_title');
					}
					var $tr = $('<tr>').append(
						$('<td>').text(col),
						$('<td>').append(sel.data('col', col))
					);
					$tbody.append($tr);
				});
			}

			function getMapping() {
				var m = {};
				$('#csc-mapping-table .csc-field-map').each(function(){
					var col = $(this).data('col');
					var val = $(this).val();
					if (val) m[col] = val;
				});
				return m;
			}

			$('#csc-save-mapping-btn').on('click', function(){
				var m = getMapping();
				ajax('csc_import_save_mapping', { mapping: JSON.stringify(m) }, function(res){
					$('#csc-mapping-result')
						.css('color', res.success ? 'green' : 'red')
						.text(res.success ? 'Mapping saved.' : 'Error saving mapping.');
				});
			});

			$('#csc-preview-btn').on('click', function(){
				var m = getMapping();
				if (!Object.values(m).includes('email')) {
					alert('Please map a column to "Email (required)" before previewing.');
					return;
				}
				$(this).prop('disabled', true).text('Loading preview…');
				var $btn = $(this);
				ajax('csc_import_preview', { mapping: JSON.stringify(m) }, function(res){
					$btn.prop('disabled', false).text('Preview Import');
					if (!res.success) {
						$('#csc-mapping-result').css('color','red').text(res.data ? res.data.message : 'Error');
						return;
					}
					previewRows = res.data.rows;
					renderPreviewTable(previewRows);
					$('#csc-step-mapping').hide();
					$('#csc-step-preview').show();
				});
			});

			/* ----- Step 3: Preview ----- */
			function renderPreviewTable(rows) {
				var html = '<table class="wp-list-table widefat fixed striped" style="font-size:13px;">';
				html += '<thead><tr><th>#</th><th>Name</th><th>Email</th><th>Company</th><th>Job Title</th><th>Action</th></tr></thead><tbody>';
				rows.forEach(function(r, i){
					var nameFlag = '';
					var action = r.action;
					var actionColor = r.action_type === 'create' ? '#16a34a' : (r.action_type === 'skip' ? '#6b7280' : '#d97706');
					html += '<tr>';
					html += '<td>' + (i+1) + '</td>';
					html += '<td>' + esc(r.first_name) + ' ' + esc(r.last_name) + '</td>';
					html += '<td>' + esc(r.email) + (r.user_exists ? ' <span style="color:#d97706;" title="User already exists">⚠</span>' : '') + '</td>';
					html += '<td>' + esc(r.org_name) + (r.org_exists ? ' <span style="color:#d97706;" title="Company already exists">⚠</span>' : '') + '</td>';
					html += '<td>' + esc(r.job_title) + '</td>';
					html += '<td style="color:' + actionColor + ';font-weight:600;">' + esc(action) + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				html += '<p style="margin-top:8px;color:#6b7280;">⚠ = already exists in portal</p>';
				$('#csc-preview-table-wrap').html(html);
			}

			function esc(s) {
				return $('<span>').text(s || '').html();
			}

			$('#csc-back-btn').on('click', function(){
				$('#csc-step-preview').hide();
				$('#csc-step-mapping').show();
			});

			/* ----- Step 4: Import ----- */
			$('#csc-confirm-import-btn').on('click', function(){
				$('#csc-step-preview').hide();
				$('#csc-step-results').show();
				importLog = [];
				$('#csc-result-rows').empty();
				$('#csc-result-table-wrap').hide();
				$('#csc-result-summary').hide().empty();

				var options = {
					status:          $('#opt-status').val(),
					send_email:      $('#opt-send-email').is(':checked') ? '1' : '0',
					company_admin:   $('#opt-company-admin').is(':checked') ? '1' : '0',
					sync_hubspot:    $('#opt-hubspot').is(':checked') ? '1' : '0',
					skip_existing:   $('#opt-skip-existing').is(':checked') ? '1' : '0',
				};

				var total   = previewRows.length;
				var batchSz = 20;
				var offset  = 0;
				var seenOrgs = {}; // track first user per org for company admin

				updateProgress(0, total);

				function runBatch() {
					var batch = previewRows.slice(offset, offset + batchSz);
					if (batch.length === 0) {
						finishImport(total);
						return;
					}
					ajax('csc_import_run_batch', {
						rows:     JSON.stringify(batch),
						options:  JSON.stringify(options),
						seen_orgs: JSON.stringify(seenOrgs),
					}, function(res){
						if (res.success) {
							res.data.results.forEach(function(r){
								importLog.push(r);
								appendResultRow(r);
								if (r.action === 'Created' && r.org_name) {
									seenOrgs[r.org_name] = true;
								}
							});
							// Update seenOrgs from server-side tracking
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
				var $tr = $('<tr>').append(
					$('<td>').text(importLog.length),
					$('<td>').text(r.email || ''),
					$('<td>').text(r.org_name || ''),
					$('<td>').css({ color: color, fontWeight: '600' }).text(r.action + (r.error ? ': ' + r.error : ''))
				);
				$('#csc-result-rows').append($tr);
			}

			function finishImport(total) {
				$('#csc-progress-label').text('Done! ' + total + ' rows processed.');
				$('#csc-progress-bar').css({ width: '100%', background: '#16a34a' });

				// Summary
				var counts = { Created: 0, Skipped: 0, Updated: 0, Error: 0 };
				importLog.forEach(function(r){
					var key = r.action.indexOf('Error') !== -1 ? 'Error' : r.action;
					if (counts[key] !== undefined) counts[key]++;
				});
				var sumHtml = '<p><strong>Summary:</strong> ';
				sumHtml += counts.Created + ' created, ';
				sumHtml += counts.Skipped + ' skipped, ';
				sumHtml += counts.Updated + ' updated, ';
				sumHtml += counts.Error + ' errors.</p>';
				$('#csc-result-summary').html(sumHtml).show();
				$('#csc-result-table-wrap').show();

				// Show email queue section
				refreshEqStats();
				$('#csc-eq-section').show();
			}

			/* ----- Download log ----- */
			$('#csc-download-log').on('click', function(){
				var csv = 'Email,Company,Action\n';
				importLog.forEach(function(r){
					csv += '"' + (r.email||'').replace(/"/g,'""') + '","' + (r.org_name||'').replace(/"/g,'""') + '","' + (r.action||'').replace(/"/g,'""') + '"\n';
				});
				var blob = new Blob([csv], { type: 'text/csv' });
				var a    = document.createElement('a');
				a.href   = URL.createObjectURL(blob);
				a.download = 'import-log.csv';
				a.click();
			});

			/* ----- Email queue controls on results page ----- */
			function refreshEqStats() {
				ajax('csc_eq_stats', { nonce: '<?php echo esc_js( wp_create_nonce( "csc_integrations_nonce" ) ); ?>' }, function(res){
					if (res.success) $('#csc-eq-stats-import').html(res.data.html);
				});
			}

			$('#csc-eq-send-all-import').on('click', function(){
				if (!confirm('Send all queued emails now?')) return;
				$(this).prop('disabled', true);
				ajax('csc_eq_send_all', { nonce: '<?php echo esc_js( wp_create_nonce( "csc_integrations_nonce" ) ); ?>' }, function(){
					$('#csc-eq-send-all-import').prop('disabled', false);
					refreshEqStats();
				});
			});

			$('#csc-eq-pause-import').on('click', function(){
				var paused = $(this).data('paused');
				var action = paused ? 'csc_eq_resume' : 'csc_eq_pause';
				ajax(action, { nonce: '<?php echo esc_js( wp_create_nonce( "csc_integrations_nonce" ) ); ?>' }, function(){
					refreshEqStats();
					if (paused) {
						$('#csc-eq-pause-import').data('paused', 0).text('Pause Queue');
					} else {
						$('#csc-eq-pause-import').data('paused', 1).text('Resume Queue');
					}
				});
			});

		});
		</script>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Upload CSV and return headers + temp storage key
	 * --------------------------------------------------------------------- */

	public function ajax_upload() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( array( 'message' => 'No file received.' ) );
		}

		$file     = $_FILES['csv_file'];
		$tmp_path = $file['tmp_name'];
		$ext      = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( $ext !== 'csv' ) {
			wp_send_json_error( array( 'message' => 'Only CSV files are supported.' ) );
		}

		$rows    = $this->parse_csv( $tmp_path );
		$headers = ! empty( $rows ) ? array_keys( $rows[0] ) : array();

		if ( empty( $headers ) ) {
			wp_send_json_error( array( 'message' => 'The CSV appears to be empty or malformed.' ) );
		}

		// Cache rows in a transient for the preview/import steps
		$key = 'csc_import_' . get_current_user_id();
		set_transient( $key, $rows, HOUR_IN_SECONDS );

		wp_send_json_success( array( 'headers' => $headers ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Save column mapping
	 * --------------------------------------------------------------------- */

	public function ajax_save_mapping() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$raw     = sanitize_text_field( wp_unslash( $_POST['mapping'] ?? '' ) );
		$mapping = json_decode( $raw, true );
		if ( ! is_array( $mapping ) ) {
			wp_send_json_error( array( 'message' => 'Invalid mapping data.' ) );
		}

		update_option( 'csc_import_column_mapping', $mapping );
		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	 * AJAX: Preview (returns mapped rows with user/org existence flags)
	 * --------------------------------------------------------------------- */

	public function ajax_preview() {
		check_ajax_referer( 'csc_import_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}

		$raw     = sanitize_text_field( wp_unslash( $_POST['mapping'] ?? '' ) );
		$mapping = json_decode( $raw, true );
		if ( ! is_array( $mapping ) ) {
			wp_send_json_error( array( 'message' => 'Invalid mapping.' ) );
		}

		$key  = 'csc_import_' . get_current_user_id();
		$rows = get_transient( $key );
		if ( ! $rows ) {
			wp_send_json_error( array( 'message' => 'Session expired. Please re-upload the CSV.' ) );
		}

		$preview = array();
		foreach ( $rows as $row ) {
			$mapped      = $this->map_row( $row, $mapping );
			$email       = strtolower( trim( $mapped['email'] ?? '' ) );
			$org_name    = trim( $mapped['org_name'] ?? '' );
			$user_exists = $email && email_exists( $email );
			$org_exists  = $org_name && $this->find_org( $org_name );

			$action_type = 'create';
			$action      = 'Create user + ' . ( $org_exists ? 'link to existing company' : 'create company' );

			if ( ! $email ) {
				$action_type = 'skip';
				$action      = 'Skip (no email)';
			} elseif ( $user_exists ) {
				$action_type = 'skip';
				$action      = 'Skip (user exists)';
			}

			$preview[] = array_merge( $mapped, array(
				'user_exists' => (bool) $user_exists,
				'org_exists'  => (bool) $org_exists,
				'action'      => $action,
				'action_type' => $action_type,
			) );
		}

		// Store mapping in transient so run_batch can use it
		set_transient( $key . '_mapping', $mapping, HOUR_IN_SECONDS );

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

		$rows_raw = sanitize_text_field( wp_unslash( $_POST['rows'] ?? '' ) );
		$opts_raw = sanitize_text_field( wp_unslash( $_POST['options'] ?? '' ) );
		$orgs_raw = sanitize_text_field( wp_unslash( $_POST['seen_orgs'] ?? '{}' ) );

		$rows     = json_decode( $rows_raw, true );
		$opts     = json_decode( $opts_raw, true );
		$seen_orgs = json_decode( $orgs_raw, true ) ?: array();

		if ( ! is_array( $rows ) || ! is_array( $opts ) ) {
			wp_send_json_error( array( 'message' => 'Invalid batch data.' ) );
		}

		$hs     = new Csc_Hubspot();
		$eq     = new Csc_Email_Queue();
		$results = array();

		$default_status  = in_array( $opts['status'] ?? '', array( 'approved', 'pending' ), true ) ? $opts['status'] : 'approved';
		$send_email      = ( $opts['send_email'] ?? '0' ) === '1';
		$company_admin   = ( $opts['company_admin'] ?? '1' ) === '1';
		$sync_hubspot    = ( $opts['sync_hubspot'] ?? '0' ) === '1';
		$skip_existing   = ( $opts['skip_existing'] ?? '1' ) === '1';

		foreach ( $rows as $row ) {
			$result = $this->import_row( $row, array(
				'default_status' => $default_status,
				'send_email'     => $send_email,
				'company_admin'  => $company_admin,
				'sync_hubspot'   => $sync_hubspot,
				'skip_existing'  => $skip_existing,
				'seen_orgs'      => &$seen_orgs,
				'hs'             => $hs,
				'eq'             => $eq,
			) );

			$results[] = $result;
		}

		wp_send_json_success( array(
			'results'   => $results,
			'seen_orgs' => $seen_orgs,
		) );
	}

	/* -----------------------------------------------------------------------
	 * Core: import a single row
	 * --------------------------------------------------------------------- */

	private function import_row( $row, $ctx ) {
		$email     = strtolower( trim( $row['email'] ?? '' ) );
		$first     = sanitize_text_field( $row['first_name'] ?? '' );
		$last      = sanitize_text_field( $row['last_name'] ?? '' );
		$phone     = sanitize_text_field( $row['phone'] ?? '' );
		$org_name  = sanitize_text_field( $row['org_name'] ?? '' );
		$job_title = sanitize_text_field( $row['job_title'] ?? '' );

		// Per-row overrides
		$row_status       = in_array( $row['status'] ?? '', array( 'approved', 'pending' ), true ) ? $row['status'] : $ctx['default_status'];
		$row_send_email   = isset( $row['send_email'] ) && $row['send_email'] !== '' ? ( $row['send_email'] === '1' ) : $ctx['send_email'];
		$row_company_admin = isset( $row['is_company_admin'] ) && $row['is_company_admin'] !== '' ? ( $row['is_company_admin'] === '1' ) : $ctx['company_admin'];
		$row_sync_hubspot  = isset( $row['sync_to_hubspot'] ) && $row['sync_to_hubspot'] !== '' ? ( $row['sync_to_hubspot'] === '1' ) : $ctx['sync_hubspot'];

		$log = array(
			'email'    => $email,
			'org_name' => $org_name,
			'action'   => '',
			'error'    => '',
		);

		// 1. Validate
		if ( ! $email || ! is_email( $email ) ) {
			$log['action'] = 'Skip';
			$log['error']  = 'Invalid or missing email';
			return $log;
		}

		// 2. Check existing
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			if ( $ctx['skip_existing'] ) {
				$log['action'] = 'Skipped';
				return $log;
			}
			// Update mode
			$user_id = $existing_user->ID;
			$this->save_user_meta( $user_id, $row, $job_title, $phone );
			$log['action'] = 'Updated';
		} else {
			// 3. Find or create org
			$org_id = $org_name ? $this->find_or_create_org( $org_name ) : 0;

			// 4. Create user
			$user_id = wp_insert_user( array(
				'user_login'   => $email,
				'user_email'   => $email,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => trim( $first . ' ' . $last ),
				'user_pass'    => wp_generate_password( 24 ),
				'role'         => 'subscriber',
			) );

			if ( is_wp_error( $user_id ) ) {
				$log['action'] = 'Error';
				$log['error']  = $user_id->get_error_message();
				return $log;
			}

			// 5. Save meta
			update_user_meta( $user_id, '_csc_status', $row_status );
			update_user_meta( $user_id, '_csc_organisation_id', $org_id );
			$this->save_user_meta( $user_id, $row, $job_title, $phone );

			// Security defaults
			update_user_meta( $user_id, '_csc_2fa_enabled', '0' );
			update_user_meta( $user_id, '_csc_login_alerts', '0' );

			// Company admin — first user per org
			if ( $row_company_admin && $org_name && empty( $ctx['seen_orgs'][ $org_name ] ) ) {
				update_user_meta( $user_id, '_csc_can_edit_company', '1' );
				$ctx['seen_orgs'][ $org_name ] = true;
			} else {
				update_user_meta( $user_id, '_csc_can_edit_company', '0' );
			}

			// 6. Queue set-password email
			if ( $row_send_email ) {
				$this->queue_approval_email( $user_id, $ctx['eq'] );
			}

			// 7. Sync to HubSpot
			if ( $row_sync_hubspot && get_option( 'csc_hubspot_token', '' ) ) {
				$ctx['hs']->sync_contact( $user_id );
			}

			$log['action'] = 'Created';
		}

		return $log;
	}

	/**
	 * Save common user meta fields.
	 */
	private function save_user_meta( $user_id, $row, $job_title, $phone ) {
		if ( $phone )     update_user_meta( $user_id, '_csc_phone', $phone );
		if ( $job_title ) update_user_meta( $user_id, '_csc_job_title', $job_title );

		// Consent fields
		$consent_dir = ( ( $row['consent_directory'] ?? '' ) === '1' || strtolower( $row['consent_directory'] ?? '' ) === 'yes' ) ? '1' : '0';
		$consent_shr = ( ( $row['consent_sharing'] ?? '' ) === '1' || strtolower( $row['consent_sharing'] ?? '' ) === 'yes' ) ? '1' : '0';
		$consent_mkt = ( ( $row['consent_marketing'] ?? '' ) === '1' || strtolower( $row['consent_marketing'] ?? '' ) === 'yes' ) ? '1' : '0';

		update_user_meta( $user_id, '_csc_consent_directory', $consent_dir );
		update_user_meta( $user_id, '_csc_dir_org_visible',   $consent_dir );
		update_user_meta( $user_id, '_csc_dir_profile_visible', $consent_dir );
		update_user_meta( $user_id, '_csc_consent_sharing',   $consent_shr );
		update_user_meta( $user_id, '_csc_consent_marketing', $consent_mkt );
		update_user_meta( $user_id, '_csc_notif_newsletter',  $consent_mkt );
	}

	/**
	 * Queue the approval email (set-password link) for a user.
	 */
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
		$body    = 'Dear ' . $user->first_name . ",\n\n";
		$body   .= "We are pleased to confirm that your application to join the Celtic Sea Cluster has been approved.\n\n";
		$body   .= "You can now set your password and access the Members Portal using the link below:\n";
		$body   .= $reset_url . "\n\n";
		$body   .= "Once your password has been created, you will be able to log in here:\n";
		$body   .= $login_url . "\n\n";
		$body   .= "Within the portal, you can create and manage your member profile, access the Member Directory, connect with other members through the forum, and view the latest newsletters and resources.\n\n";
		$body   .= "Welcome to the Celtic Sea Cluster. We are delighted to have you as part of the network.\n\n";
		$body   .= "Kind regards,\n\nThe Celtic Sea Cluster Team\n";

		$eq->enqueue( $user_id, $user->user_email, $subject, $body );
	}

	/* -----------------------------------------------------------------------
	 * Helpers: CSV parse + org lookup
	 * --------------------------------------------------------------------- */

	/**
	 * Parse a CSV file into an array of associative arrays (header => value).
	 */
	private function parse_csv( $file_path ) {
		$rows   = array();
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) return $rows;

		// Handle BOM
		$bom = fread( $handle, 3 );
		if ( $bom !== "\xEF\xBB\xBF" ) {
			rewind( $handle );
		}

		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return $rows;
		}

		// Trim header names
		$headers = array_map( 'trim', $headers );

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( count( $data ) !== count( $headers ) ) {
				// Pad or trim
				$data = array_slice( array_pad( $data, count( $headers ), '' ), 0, count( $headers ) );
			}
			$rows[] = array_combine( $headers, array_map( 'trim', $data ) );
		}

		fclose( $handle );
		return $rows;
	}

	/**
	 * Map a raw CSV row to portal field names using the column mapping.
	 */
	private function map_row( $row, $mapping ) {
		$out = array();
		foreach ( $mapping as $csv_col => $field ) {
			if ( $field && isset( $row[ $csv_col ] ) ) {
				$out[ $field ] = $row[ $csv_col ];
			}
		}
		return $out;
	}

	/**
	 * Find an organisation post by exact title. Returns post ID or null.
	 */
	private function find_org( $name ) {
		$posts = get_posts( array(
			'post_type'      => 'csc_organisation',
			'post_status'    => 'publish',
			'title'          => $name,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Find or create an organisation post. Returns post ID.
	 */
	private function find_or_create_org( $name ) {
		$existing = $this->find_org( $name );
		if ( $existing ) return $existing;

		return wp_insert_post( array(
			'post_type'   => 'csc_organisation',
			'post_title'  => $name,
			'post_status' => 'publish',
		) );
	}
}
