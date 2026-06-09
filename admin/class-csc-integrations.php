<?php
/**
 * Integrations admin page — HubSpot token, settings, queue status, error log.
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/admin
 */
class Csc_Integrations {

	public function register_hooks( $loader ) {
		$loader->add_action( 'admin_menu',              $this, 'add_admin_menu' );
		$loader->add_action( 'wp_ajax_csc_hs_save_settings', $this, 'ajax_save_settings' );
		$loader->add_action( 'wp_ajax_csc_hs_test',    $this, 'ajax_test_connection' );
		$loader->add_action( 'wp_ajax_csc_hs_retry',   $this, 'ajax_retry_failed' );
		$loader->add_action( 'wp_ajax_csc_hs_clear_errors', $this, 'ajax_clear_errors' );
		$loader->add_action( 'wp_ajax_csc_eq_pause',   $this, 'ajax_eq_pause' );
		$loader->add_action( 'wp_ajax_csc_eq_resume',  $this, 'ajax_eq_resume' );
		$loader->add_action( 'wp_ajax_csc_eq_send_all', $this, 'ajax_eq_send_all' );
		$loader->add_action( 'wp_ajax_csc_eq_retry',   $this, 'ajax_eq_retry' );
		$loader->add_action( 'wp_ajax_csc_eq_clear',   $this, 'ajax_eq_clear' );
		$loader->add_action( 'wp_ajax_csc_eq_stats',   $this, 'ajax_eq_stats' );
	}

	/* -----------------------------------------------------------------------
	 * Menu
	 * --------------------------------------------------------------------- */

	public function add_admin_menu() {
		add_submenu_page(
			'csc-members',
			'Integrations',
			'Integrations',
			'manage_options',
			'csc-integrations',
			array( $this, 'render_page' )
		);
	}

	/* -----------------------------------------------------------------------
	 * Page render
	 * --------------------------------------------------------------------- */

	public function render_page() {
		$hs    = new Csc_Hubspot();
		$eq    = new Csc_Email_Queue();
		$token = get_option( 'csc_hubspot_token', '' );
		$auto  = get_option( 'csc_hubspot_auto_sync', '1' );
		$batch = get_option( 'csc_email_queue_batch_size', 5 );
		$interval = get_option( 'csc_email_queue_interval', 2 );
		$hs_batch = get_option( 'csc_hubspot_queue_batch_size', 20 );
		$hs_interval = get_option( 'csc_hubspot_queue_interval', 2 );
		$errors = $hs->get_errors( 20 );
		$eq_stats = $eq->get_stats();
		$eq_paused = $eq->is_paused();
		$nonce = wp_create_nonce( 'csc_integrations_nonce' );
		?>
		<div class="wrap">
			<h1>Integrations</h1>

			<!-- HubSpot Settings -->
			<div class="csc-int-section card" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
				<h2 style="margin-top:0;">HubSpot</h2>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="csc-hs-token">Private App Token</label></th>
						<td>
							<input type="password" id="csc-hs-token" class="regular-text"
								value="<?php echo esc_attr( $token ); ?>" autocomplete="off" />
							<button type="button" class="button" id="csc-hs-test">Test Connection</button>
							<span id="csc-hs-test-result" style="margin-left:8px;"></span>
						</td>
					</tr>
					<tr>
						<th scope="row">Auto-sync new members</th>
						<td>
							<label>
								<input type="checkbox" id="csc-hs-auto"
									<?php checked( $auto, '1' ); ?> />
								Automatically sync to HubSpot when a member is approved
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Import — contacts per batch</th>
						<td>
							<input type="number" id="csc-hs-batch" value="<?php echo esc_attr( $hs_batch ); ?>"
								min="1" max="50" class="small-text" />
							<p class="description">HubSpot API calls per cron run during bulk import (max 50).</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Import — minutes between batches</th>
						<td>
							<input type="number" id="csc-hs-interval" value="<?php echo esc_attr( $hs_interval ); ?>"
								min="1" max="60" class="small-text" />
						</td>
					</tr>
				</table>

				<p>
					<button type="button" class="button button-primary" id="csc-hs-save">Save HubSpot Settings</button>
					<span id="csc-hs-save-result" style="margin-left:8px;"></span>
				</p>

				<?php if ( $errors ) : ?>
				<h3>Recent Sync Errors</h3>
				<table class="wp-list-table widefat fixed striped" style="margin-bottom:12px;">
					<thead><tr>
						<th>Time</th><th>User ID</th><th>Error</th>
					</tr></thead>
					<tbody>
					<?php foreach ( array_reverse( $errors ) as $err ) : ?>
						<tr>
							<td><?php echo esc_html( $err['timestamp'] ); ?></td>
							<td><?php echo esc_html( $err['user_id'] ); ?></td>
							<td><?php echo esc_html( $err['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<button type="button" class="button" id="csc-hs-retry">Retry Failed Syncs</button>
				<button type="button" class="button" id="csc-hs-clear-errors" style="margin-left:8px;">Clear Error Log</button>
				<span id="csc-hs-retry-result" style="margin-left:8px;"></span>
				<?php endif; ?>
			</div>

			<!-- Email Queue -->
			<div class="csc-int-section card" style="max-width:800px;padding:20px 24px;margin-bottom:24px;">
				<h2 style="margin-top:0;">Email Queue</h2>

				<div id="csc-eq-stats" style="margin-bottom:16px;">
					<?php $this->render_eq_stats( $eq_stats, $eq_paused ); ?>
				</div>

				<p>
					<?php if ( $eq_paused ) : ?>
						<button type="button" class="button button-primary" id="csc-eq-resume">Resume Queue</button>
					<?php else : ?>
						<button type="button" class="button" id="csc-eq-pause">Pause Queue</button>
					<?php endif; ?>
					<button type="button" class="button" id="csc-eq-send-all" style="margin-left:8px;">Send All Now</button>
					<button type="button" class="button" id="csc-eq-clear" style="margin-left:8px;">Clear Queue</button>
					<span id="csc-eq-result" style="margin-left:8px;"></span>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="csc-eq-batch">Emails per batch</label></th>
						<td>
							<input type="number" id="csc-eq-batch" value="<?php echo esc_attr( $batch ); ?>"
								min="1" max="50" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="csc-eq-interval">Minutes between batches</label></th>
						<td>
							<input type="number" id="csc-eq-interval" value="<?php echo esc_attr( $interval ); ?>"
								min="1" max="60" class="small-text" />
						</td>
					</tr>
				</table>
				<button type="button" class="button button-primary" id="csc-eq-save">Save Email Queue Settings</button>
				<span id="csc-eq-save-result" style="margin-left:8px;"></span>
			</div>
		</div>

		<script>
		jQuery(function($){
			var nonce = '<?php echo esc_js( $nonce ); ?>';

			function ajax(action, data, cb) {
				data = $.extend({ action: action, nonce: nonce }, data);
				$.post(ajaxurl, data).done(function(res){
					cb(res);
				}).fail(function(){
					cb({ success: false, data: { message: 'Network error.' } });
				});
			}

			// Test connection
			$('#csc-hs-test').on('click', function(){
				$(this).prop('disabled', true).text('Testing…');
				ajax('csc_hs_test', { token: $('#csc-hs-token').val() }, function(res){
					$('#csc-hs-test').prop('disabled', false).text('Test Connection');
					var ok = res.success && res.data.ok;
					$('#csc-hs-test-result')
						.css('color', ok ? 'green' : 'red')
						.text(res.data ? res.data.message : 'Error');
				});
			});

			// Save HubSpot settings
			$('#csc-hs-save').on('click', function(){
				ajax('csc_hs_save_settings', {
					token:       $('#csc-hs-token').val(),
					auto_sync:   $('#csc-hs-auto').is(':checked') ? '1' : '0',
					hs_batch:    $('#csc-hs-batch').val(),
					hs_interval: $('#csc-hs-interval').val(),
				}, function(res){
					$('#csc-hs-save-result')
						.css('color', res.success ? 'green' : 'red')
						.text(res.success ? 'Saved.' : (res.data ? res.data.message : 'Error'));
				});
			});

			// Retry failed syncs
			$('#csc-hs-retry').on('click', function(){
				$(this).prop('disabled', true).text('Retrying…');
				ajax('csc_hs_retry', {}, function(res){
					$('#csc-hs-retry').prop('disabled', false).text('Retry Failed Syncs');
					$('#csc-hs-retry-result')
						.css('color', res.success ? 'green' : 'red')
						.text(res.success ? 'Done. Reload to see updated log.' : 'Error');
				});
			});

			// Clear error log
			$('#csc-hs-clear-errors').on('click', function(){
				if (!confirm('Clear all HubSpot error logs?')) return;
				ajax('csc_hs_clear_errors', {}, function(res){
					$('#csc-hs-retry-result').css('color','green').text('Cleared.');
				});
			});

			// Email queue controls
			$('#csc-eq-pause').on('click', function(){
				ajax('csc_eq_pause', {}, function(){ location.reload(); });
			});
			$('#csc-eq-resume').on('click', function(){
				ajax('csc_eq_resume', {}, function(){ location.reload(); });
			});
			$('#csc-eq-send-all').on('click', function(){
				if (!confirm('Send all queued emails immediately?')) return;
				$(this).prop('disabled', true).text('Sending…');
				ajax('csc_eq_send_all', {}, function(res){
					$('#csc-eq-send-all').prop('disabled', false).text('Send All Now');
					location.reload();
				});
			});
			$('#csc-eq-clear').on('click', function(){
				if (!confirm('Clear the entire email queue? Unsent emails will be discarded.')) return;
				ajax('csc_eq_clear', {}, function(){ location.reload(); });
			});

			// Save email queue settings
			$('#csc-eq-save').on('click', function(){
				ajax('csc_hs_save_settings', {
					eq_batch:    $('#csc-eq-batch').val(),
					eq_interval: $('#csc-eq-interval').val(),
				}, function(res){
					$('#csc-eq-save-result')
						.css('color', res.success ? 'green' : 'red')
						.text(res.success ? 'Saved.' : 'Error');
				});
			});

			// Auto-refresh stats every 15 seconds
			setInterval(function(){
				ajax('csc_eq_stats', {}, function(res){
					if (res.success) {
						$('#csc-eq-stats').html(res.data.html);
					}
				});
			}, 15000);
		});
		</script>
		<?php
	}

	private function render_eq_stats( $stats, $paused ) {
		$status_text = $paused ? '<span style="color:#d97706;">⏸ Paused</span>' : '<span style="color:#16a34a;">▶ Running</span>';
		echo '<p><strong>Queue status:</strong> ' . $status_text . ' &nbsp;|&nbsp; ';
		echo '<strong>Total:</strong> ' . esc_html( $stats['total'] ) . ' &nbsp;|&nbsp; ';
		echo '<strong>Sent:</strong> ' . esc_html( $stats['sent'] ) . ' &nbsp;|&nbsp; ';
		echo '<strong>Pending:</strong> ' . esc_html( $stats['pending'] ) . ' &nbsp;|&nbsp; ';
		echo '<strong>Failed:</strong> ' . esc_html( $stats['failed'] ) . '</p>';
	}

	/* -----------------------------------------------------------------------
	 * AJAX handlers
	 * --------------------------------------------------------------------- */

	private function verify() {
		check_ajax_referer( 'csc_integrations_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
		}
	}

	public function ajax_save_settings() {
		$this->verify();

		if ( isset( $_POST['token'] ) ) {
			update_option( 'csc_hubspot_token', sanitize_text_field( $_POST['token'] ) );
		}
		if ( isset( $_POST['auto_sync'] ) ) {
			update_option( 'csc_hubspot_auto_sync', sanitize_text_field( $_POST['auto_sync'] ) );
		}
		if ( isset( $_POST['hs_batch'] ) ) {
			update_option( 'csc_hubspot_queue_batch_size', max( 1, intval( $_POST['hs_batch'] ) ) );
		}
		if ( isset( $_POST['hs_interval'] ) ) {
			update_option( 'csc_hubspot_queue_interval', max( 1, intval( $_POST['hs_interval'] ) ) );
		}
		if ( isset( $_POST['eq_batch'] ) ) {
			update_option( 'csc_email_queue_batch_size', max( 1, intval( $_POST['eq_batch'] ) ) );
		}
		if ( isset( $_POST['eq_interval'] ) ) {
			update_option( 'csc_email_queue_interval', max( 1, intval( $_POST['eq_interval'] ) ) );
		}

		wp_send_json_success();
	}

	public function ajax_test_connection() {
		$this->verify();

		// Temporarily use the token from the form (pre-save test)
		$token = sanitize_text_field( $_POST['token'] ?? '' );
		if ( $token ) {
			// Test with the posted token without saving it
			$old = get_option( 'csc_hubspot_token', '' );
			update_option( 'csc_hubspot_token', $token );
			$hs     = new Csc_Hubspot();
			$result = $hs->test_connection();
			update_option( 'csc_hubspot_token', $old );
		} else {
			$hs     = new Csc_Hubspot();
			$result = $hs->test_connection();
		}

		wp_send_json_success( $result );
	}

	public function ajax_retry_failed() {
		$this->verify();
		$hs      = new Csc_Hubspot();
		$results = $hs->retry_failed();
		wp_send_json_success( array( 'results' => $results ) );
	}

	public function ajax_clear_errors() {
		$this->verify();
		$hs = new Csc_Hubspot();
		$hs->clear_errors();
		wp_send_json_success();
	}

	public function ajax_eq_pause() {
		$this->verify();
		$eq = new Csc_Email_Queue();
		$eq->pause();
		wp_send_json_success();
	}

	public function ajax_eq_resume() {
		$this->verify();
		$eq = new Csc_Email_Queue();
		$eq->resume();
		wp_send_json_success();
	}

	public function ajax_eq_send_all() {
		$this->verify();
		$eq = new Csc_Email_Queue();
		$eq->send_all_now();
		wp_send_json_success();
	}

	public function ajax_eq_retry() {
		$this->verify();
		$entry_id = sanitize_text_field( $_POST['entry_id'] ?? '' );
		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => 'Missing entry ID.' ) );
		}
		$eq = new Csc_Email_Queue();
		$eq->retry_entry( $entry_id );
		wp_send_json_success();
	}

	public function ajax_eq_clear() {
		$this->verify();
		$eq = new Csc_Email_Queue();
		$eq->clear_queue();
		wp_send_json_success();
	}

	public function ajax_eq_stats() {
		$this->verify();
		$eq     = new Csc_Email_Queue();
		$stats  = $eq->get_stats();
		$paused = $eq->is_paused();

		ob_start();
		$this->render_eq_stats( $stats, $paused );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
