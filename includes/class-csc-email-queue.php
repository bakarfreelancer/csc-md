<?php
/**
 * Email queue with WP Cron drip delivery.
 *
 * Queue entries are stored in the option `csc_email_queue` as an array.
 * Each entry: { id, user_id, to, subject, body, sent (0|1), failed (0|1), error, queued_at }
 *
 * Cron runs every 2 minutes (configurable). Each run sends up to N emails (configurable).
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Email_Queue {

	const CRON_HOOK     = 'csc_email_queue_run';
	const OPTION_QUEUE  = 'csc_email_queue';
	const OPTION_PAUSED = 'csc_email_queue_paused';

	/* -----------------------------------------------------------------------
	 * Bootstrap
	 * --------------------------------------------------------------------- */

	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'process_batch' ) );
		// Register the 2-minute interval if not already defined
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
	}

	public function add_cron_interval( $schedules ) {
		$interval = max( 1, intval( get_option( 'csc_email_queue_interval', 2 ) ) ) * 60;
		$schedules['csc_email_queue_interval'] = array(
			'interval' => $interval,
			'display'  => 'Every ' . round( $interval / 60 ) . ' minute(s) — CSC email queue',
		);
		return $schedules;
	}

	/* -----------------------------------------------------------------------
	 * Add to queue
	 * --------------------------------------------------------------------- */

	/**
	 * Add a single email to the queue.
	 *
	 * @param int    $user_id
	 * @param string $to
	 * @param string $subject
	 * @param string $body
	 */
	public function enqueue( $user_id, $to, $subject, $body ) {
		$queue   = get_option( self::OPTION_QUEUE, array() );
		$queue[] = array(
			'id'        => uniqid( 'eq_' ),
			'user_id'   => $user_id,
			'to'        => $to,
			'subject'   => $subject,
			'body'      => $body,
			'sent'      => 0,
			'failed'    => 0,
			'error'     => '',
			'queued_at' => current_time( 'mysql' ),
		);
		update_option( self::OPTION_QUEUE, $queue );

		// Schedule the cron if not already scheduled
		$this->maybe_schedule();
	}

	/* -----------------------------------------------------------------------
	 * Cron: process a batch
	 * --------------------------------------------------------------------- */

	public function process_batch() {
		if ( get_option( self::OPTION_PAUSED, 0 ) ) {
			return;
		}

		$queue     = get_option( self::OPTION_QUEUE, array() );
		$batch_size = max( 1, intval( get_option( 'csc_email_queue_batch_size', 5 ) ) );
		$sent_count = 0;

		foreach ( $queue as &$entry ) {
			if ( $entry['sent'] || $entry['failed'] ) {
				continue;
			}
			if ( $sent_count >= $batch_size ) {
				break;
			}

			$ok = wp_mail( $entry['to'], $entry['subject'], $entry['body'] );

			if ( $ok ) {
				$entry['sent'] = 1;
			} else {
				$entry['failed'] = 1;
				$entry['error']  = 'wp_mail returned false';
			}

			$sent_count++;
		}
		unset( $entry );

		update_option( self::OPTION_QUEUE, $queue );

		// If all done, unschedule the cron
		if ( $this->all_processed( $queue ) ) {
			$this->unschedule();
		}
	}

	/* -----------------------------------------------------------------------
	 * Stats
	 * --------------------------------------------------------------------- */

	/**
	 * Return counts: total, sent, failed, pending.
	 */
	public function get_stats() {
		$queue = get_option( self::OPTION_QUEUE, array() );
		$total  = count( $queue );
		$sent   = count( array_filter( $queue, fn( $e ) => $e['sent'] ) );
		$failed = count( array_filter( $queue, fn( $e ) => $e['failed'] ) );

		return array(
			'total'   => $total,
			'sent'    => $sent,
			'failed'  => $failed,
			'pending' => $total - $sent - $failed,
		);
	}

	public function get_queue() {
		return get_option( self::OPTION_QUEUE, array() );
	}

	public function is_paused() {
		return (bool) get_option( self::OPTION_PAUSED, 0 );
	}

	/* -----------------------------------------------------------------------
	 * Controls
	 * --------------------------------------------------------------------- */

	public function pause() {
		update_option( self::OPTION_PAUSED, 1 );
	}

	public function resume() {
		update_option( self::OPTION_PAUSED, 0 );
		$this->maybe_schedule();
	}

	/**
	 * Send all remaining emails immediately (bypass cron).
	 */
	public function send_all_now() {
		$queue = get_option( self::OPTION_QUEUE, array() );

		foreach ( $queue as &$entry ) {
			if ( $entry['sent'] || $entry['failed'] ) {
				continue;
			}
			$ok = wp_mail( $entry['to'], $entry['subject'], $entry['body'] );
			if ( $ok ) {
				$entry['sent'] = 1;
			} else {
				$entry['failed'] = 1;
				$entry['error']  = 'wp_mail returned false';
			}
		}
		unset( $entry );

		update_option( self::OPTION_QUEUE, $queue );
		$this->unschedule();
	}

	/**
	 * Retry a single failed entry by its ID.
	 */
	public function retry_entry( $entry_id ) {
		$queue = get_option( self::OPTION_QUEUE, array() );

		foreach ( $queue as &$entry ) {
			if ( $entry['id'] !== $entry_id ) {
				continue;
			}
			$ok = wp_mail( $entry['to'], $entry['subject'], $entry['body'] );
			if ( $ok ) {
				$entry['sent']   = 1;
				$entry['failed'] = 0;
				$entry['error']  = '';
			} else {
				$entry['error'] = 'wp_mail returned false';
			}
			break;
		}
		unset( $entry );

		update_option( self::OPTION_QUEUE, $queue );
	}

	/**
	 * Clear the entire queue (sent + unsent). Unschedules cron.
	 */
	public function clear_queue() {
		update_option( self::OPTION_QUEUE, array() );
		$this->unschedule();
	}

	/* -----------------------------------------------------------------------
	 * Cron scheduling
	 * --------------------------------------------------------------------- */

	public function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 10, 'csc_email_queue_interval', self::CRON_HOOK );
		}
	}

	public function unschedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/* -----------------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------------- */

	private function all_processed( $queue ) {
		foreach ( $queue as $entry ) {
			if ( ! $entry['sent'] && ! $entry['failed'] ) {
				return false;
			}
		}
		return true;
	}
}
