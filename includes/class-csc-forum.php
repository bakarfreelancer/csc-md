<?php
/**
 * CSC Forum — topic list, thread view, create/reply.
 *
 * Shortcode: [csc_forum]
 * CPT:       csc_forum_topic
 * Taxonomy:  csc_forum_cat
 *
 * @package    Csc_Md
 * @subpackage Csc_Md/includes
 */
class Csc_Forum {

	/* Default category slugs seeded on first run only */
	private static $seed_categories = array(
		'investments',
		'funding',
		'opportunities',
		'infrastructure',
		'knowledge',
		'recruitment',
	);

	/* Rotating colour palette for category pills (bg, text) */
	private static $cat_colors = array(
		array( 'rgba(68,189,112,0.12)',  '#2a9455' ),
		array( 'rgba(31,45,87,0.10)',    '#192d55' ),
		array( 'rgba(237,137,54,0.12)', '#c05621' ),
		array( 'rgba(90,103,216,0.12)', '#434190' ),
		array( 'rgba(214,158,46,0.15)', '#97711e' ),
		array( 'rgba(229,62,62,0.10)',  '#c53030' ),
		array( 'rgba(56,178,172,0.12)', '#285e61' ),
		array( 'rgba(159,122,234,0.12)','#553c9a' ),
	);

	/**
	 * Fetch all forum categories from the taxonomy (live from DB).
	 */
	private static function get_forum_categories() {
		$terms = get_terms( array(
			'taxonomy'   => 'csc_forum_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Returns an inline style string for a category pill based on term ID.
	 */
	private static function cat_pill_style( $term_id ) {
		$palette = self::$cat_colors;
		$c       = $palette[ absint( $term_id ) % count( $palette ) ];
		return 'background:' . $c[0] . ';color:' . $c[1] . ';';
	}

	public function register_hooks() {
		add_shortcode( 'csc_forum', array( $this, 'render' ) );
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'wp_ajax_csc_forum_create',  array( $this, 'ajax_create_topic' ) );
		add_action( 'wp_ajax_csc_forum_reply',   array( $this, 'ajax_reply' ) );
		add_action( 'wp_ajax_csc_forum_like',    array( $this, 'ajax_like' ) );
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'forum_topic';
		$vars[] = 'forum_cat';
		$vars[] = 'forum_page';
		return $vars;
	}

	/* -----------------------------------------------------------------------
	 * CPT & taxonomy
	 * --------------------------------------------------------------------- */
	public function register_cpt() {
		register_post_type( 'csc_forum_topic', array(
			'labels'            => array( 'name' => 'Forum Topics', 'singular_name' => 'Forum Topic' ),
			'public'            => false,
			'publicly_queryable'=> false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'supports'          => array( 'title', 'editor', 'author', 'comments' ),
			'menu_icon'         => 'dashicons-format-chat',
			'menu_position'     => 26,
		) );

		register_taxonomy( 'csc_forum_cat', 'csc_forum_topic', array(
			'labels'       => array( 'name' => 'Forum Categories', 'singular_name' => 'Category' ),
			'public'       => false,
			'hierarchical' => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'rewrite'      => false,
		) );

		// Seed default categories on first run
		foreach ( self::$seed_categories as $slug ) {
			if ( ! term_exists( $slug, 'csc_forum_cat' ) ) {
				wp_insert_term( ucfirst( $slug ), 'csc_forum_cat', array( 'slug' => $slug ) );
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * Shortcode entry point
	 * --------------------------------------------------------------------- */
	public function render( $atts ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( Csc_Dashboard::login_url() );
			exit;
		}
		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_safe_redirect( Csc_Dashboard::login_url() );
			exit;
		}

		$topic_id = isset( $_GET['forum_topic'] ) ? absint( $_GET['forum_topic'] ) : 0;
		if ( $topic_id ) {
			return $this->render_thread( $topic_id, $user );
		}
		return $this->render_list( $user );
	}

	/* -----------------------------------------------------------------------
	 * Topic list
	 * --------------------------------------------------------------------- */
	private function render_list( $user ) {
		$cat    = sanitize_key( $_GET['forum_cat'] ?? '' );
		$search = sanitize_text_field( $_GET['s'] ?? '' );
		$paged  = max( 1, absint( $_GET['forum_page'] ?? 1 ) );

		$forum_url  = Csc_Dashboard::portal_url( 'member-forum' );
		$forum_cats = self::get_forum_categories();
		$cat_slugs  = wp_list_pluck( $forum_cats, 'slug' );

		// Sanitise active cat against real terms
		if ( $cat && ! in_array( $cat, $cat_slugs, true ) ) {
			$cat = '';
		}

		$args = array(
			'post_type'      => 'csc_forum_topic',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $cat ) {
			$args['tax_query'] = array( array(
				'taxonomy' => 'csc_forum_cat',
				'field'    => 'slug',
				'terms'    => $cat,
			) );
		}
		if ( $search ) {
			$args['s'] = $search;
		}

		$query  = new WP_Query( $args );
		$topics = $query->posts;
		$pages  = $query->max_num_pages;

		$nonce = wp_create_nonce( 'csc_forum_nonce' );

		ob_start();
		?>
		<div class="csc-member-portal">
			<?php echo Csc_Dashboard::render_sidebar( 'forum', $user ); ?>

			<main class="csc-portal-main">

				<!-- Page header -->
				<div class="csc-forum-header">
					<p class="csc-forum-tagline">A space to share knowledge, explore opportunities, and collaborate with other members.</p>
					<button class="csc-btn-primary csc-forum-create-btn" id="csc-forum-create-btn">Create a Conversation</button>
				</div>

				<!-- Search -->
				<form method="GET" action="<?php echo esc_url( $forum_url ); ?>" class="csc-forum-search-form">
					<?php if ( $cat ) : ?><input type="hidden" name="forum_cat" value="<?php echo esc_attr( $cat ); ?>"><?php endif; ?>
					<div class="csc-dir-search-wrap">
						<svg class="csc-dir-search-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5"/><line x1="13" y1="13" x2="18" y2="18"/></svg>
						<input type="search" name="s" class="csc-dir-search-input" autocomplete="off"
							placeholder="Search Discussion…" value="<?php echo esc_attr( $search ); ?>">
					</div>
				</form>

				<!-- Category tabs -->
				<div class="csc-forum-cats">
					<a href="<?php echo esc_url( $forum_url ); ?>" class="csc-forum-cat-tab <?php echo ! $cat ? 'is-active' : ''; ?>">All</a>
					<?php foreach ( $forum_cats as $term ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'forum_cat', $term->slug, $forum_url ) ); ?>"
					   class="csc-forum-cat-tab <?php echo $cat === $term->slug ? 'is-active' : ''; ?>">
						<?php echo esc_html( $term->name ); ?>
					</a>
					<?php endforeach; ?>
				</div>

				<!-- Topic list -->
				<div class="csc-forum-list">
					<?php if ( empty( $topics ) ) : ?>
					<div class="csc-dir-empty">
						<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="24" cy="24" r="20"/><path d="M16 28 Q24 20 32 28"/><circle cx="17" cy="22" r="2" fill="currentColor"/><circle cx="31" cy="22" r="2" fill="currentColor"/></svg>
						<p>No conversations yet. Be the first to start one!</p>
					</div>
					<?php else : ?>
					<?php foreach ( $topics as $topic ) :
						$topic_cats   = wp_get_post_terms( $topic->ID, 'csc_forum_cat' );
						$topic_cat    = ! is_wp_error( $topic_cats ) && ! empty( $topic_cats ) ? $topic_cats[0] : null;
						$reply_count  = get_comments_number( $topic->ID );
						$likes        = (int) get_post_meta( $topic->ID, '_csc_forum_likes', true );
						$author       = get_userdata( $topic->post_author );
						$author_name  = $author ? trim( get_user_meta( $author->ID, 'first_name', true ) . ' ' . get_user_meta( $author->ID, 'last_name', true ) ) : '';
						$author_name  = $author_name ?: ( $author ? $author->display_name : 'Member' );
						$photo_id     = $author ? get_user_meta( $author->ID, '_csc_profile_photo_id', true ) : '';
						$photo_url    = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
						$av_init      = $author_name ? strtoupper( substr( $author_name, 0, 1 ) ) : 'M';
						$topic_url    = add_query_arg( 'forum_topic', $topic->ID, $forum_url );
						$excerpt      = wp_trim_words( wp_strip_all_tags( $topic->post_content ), 20, '…' );
						$time_ago     = human_time_diff( strtotime( $topic->post_date ), current_time( 'timestamp' ) ) . ' ago';
						$liked        = in_array( $user->ID, array_map( 'intval', (array) get_post_meta( $topic->ID, '_csc_forum_liked_by', false ) ), true );
					?>
					<a href="<?php echo esc_url( $topic_url ); ?>" class="csc-forum-topic">
						<div class="csc-forum-topic__main">
							<?php if ( $topic_cat ) : ?>
							<span class="csc-forum-topic-cat" style="<?php echo esc_attr( self::cat_pill_style( $topic_cat->term_id ) ); ?>"><?php echo esc_html( $topic_cat->name ); ?></span>
							<?php endif; ?>
							<h3 class="csc-forum-topic__title"><?php echo esc_html( $topic->post_title ); ?></h3>
							<?php if ( $excerpt ) : ?>
							<p class="csc-forum-topic__excerpt"><?php echo esc_html( $excerpt ); ?></p>
							<?php endif; ?>
							<div class="csc-forum-topic__meta">
								<span class="csc-forum-topic__author">
									<span class="csc-forum-topic__avatar">
										<?php if ( $photo_url ) : ?>
										<img src="<?php echo esc_url( $photo_url ); ?>" alt="">
										<?php else : ?>
										<?php echo esc_html( $av_init ); ?>
										<?php endif; ?>
									</span>
									<?php echo esc_html( $author_name ); ?>
								</span>
								<span class="csc-forum-topic__time"><?php echo esc_html( $time_ago ); ?></span>
							</div>
						</div>
						<div class="csc-forum-topic__stats">
							<span class="csc-forum-topic__stat">
								<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 4h16c.55 0 1 .45 1 1v8c0 .55-.45 1-1 1H4l-3 3V5c0-.55.45-1 1-1z"/></svg>
								<?php echo esc_html( $reply_count ); ?>
							</span>
							<span class="csc-forum-topic__stat <?php echo $liked ? 'is-liked' : ''; ?>">
								<svg viewBox="0 0 20 20" fill="<?php echo $liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17s-7-4.5-7-9a5 5 0 0 1 7-4.58A5 5 0 0 1 17 8c0 4.5-7 9-7 9z"/></svg>
								<?php echo esc_html( $likes ); ?>
							</span>
						</div>
					</a>
					<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Pagination -->
				<?php if ( $pages > 1 ) : ?>
				<div class="csc-dir-pagination">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'forum_page', '%#%', $forum_url ),
						'format'    => '',
						'current'   => $paged,
						'total'     => $pages,
						'prev_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="10 4 6 8 10 12"/></svg>',
						'next_text' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 4 10 8 6 12"/></svg>',
						'type'      => 'list',
					) );
					?>
				</div>
				<?php endif; ?>

			</main>
		</div>

		<!-- Create Conversation Modal -->
		<div class="csc-modal-overlay" id="csc-forum-modal" hidden aria-modal="true" role="dialog" aria-labelledby="csc-forum-modal-title">
			<div class="csc-modal csc-forum-modal">
				<button class="csc-modal-close" id="csc-forum-modal-close" aria-label="Close">&times;</button>
				<h2 class="csc-forum-modal-title" id="csc-forum-modal-title">Start a conversation</h2>
				<p class="csc-forum-modal-notice">
					<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="10" cy="10" r="8"/><line x1="10" y1="6" x2="10" y2="10"/><circle cx="10" cy="14" r=".5" fill="currentColor"/></svg>
					You have conversations in the Forum that provide great value/topic
				</p>
				<form id="csc-forum-create-form">
					<?php wp_nonce_field( 'csc_forum_nonce', 'csc_forum_nonce' ); ?>
					<div class="csc-form-group">
						<label class="csc-label" for="csc-forum-title-input">Title</label>
						<input type="text" id="csc-forum-title-input" name="topic_title" class="csc-input" placeholder="e.g. Looking for fabrication partners in South Wales" required>
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="csc-forum-body-input">Body</label>
						<textarea id="csc-forum-body-input" name="topic_body" class="csc-input csc-textarea" rows="5" placeholder="Share the context, question, or opportunity to share…" required></textarea>
					</div>
					<div class="csc-form-group">
						<label class="csc-label" for="csc-forum-cat-select">Category</label>
						<select id="csc-forum-cat-select" name="topic_cat" class="csc-input csc-select">
							<option value="">— Select a category —</option>
							<?php foreach ( $forum_cats as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="csc-form-group">
						<label class="csc-label">Tags</label>
						<div class="csc-tag-input-wrap" id="csc-forum-tag-wrap">
							<div class="csc-tag-chips" id="csc-forum-tag-chips"></div>
							<input type="text" id="csc-forum-tag-input" class="csc-tag-input" placeholder="Add a tag and press Enter">
							<input type="hidden" name="topic_tags" id="csc-forum-tags-hidden">
						</div>
						<span class="csc-forum-tag-count" id="csc-forum-tag-count">0 / 5</span>
					</div>
					<p class="csc-forum-modal-error" id="csc-forum-modal-error" hidden></p>
					<div class="csc-forum-modal-actions">
						<button type="button" class="csc-btn-outline" id="csc-forum-cancel-btn">Cancel</button>
						<button type="submit" class="csc-btn-primary" id="csc-forum-submit-btn">Start Conversation</button>
					</div>
				</form>
			</div>
		</div>

		<script>
		(function(){
			var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce   = <?php echo wp_json_encode( $nonce ); ?>;
			var forumUrl = <?php echo wp_json_encode( $forum_url ); ?>;

			// Open / close modal
			var modal = document.getElementById('csc-forum-modal');
			function openModal() { modal.removeAttribute('hidden'); document.body.style.overflow = 'hidden'; }
			function closeModal() { modal.setAttribute('hidden',''); document.body.style.overflow = ''; }
			document.getElementById('csc-forum-create-btn').addEventListener('click', openModal);
			document.getElementById('csc-forum-modal-close').addEventListener('click', closeModal);
			document.getElementById('csc-forum-cancel-btn').addEventListener('click', closeModal);
			modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });

			// Tag input
			var tags = [];
			var tagInput = document.getElementById('csc-forum-tag-input');
			var tagChips = document.getElementById('csc-forum-tag-chips');
			var tagsHidden = document.getElementById('csc-forum-tags-hidden');
			var tagCount = document.getElementById('csc-forum-tag-count');
			function renderTags() {
				tagChips.innerHTML = '';
				tags.forEach(function(t, i){
					var chip = document.createElement('span');
					chip.className = 'csc-tag-chip';
					chip.innerHTML = t + '<button type="button" class="csc-tag-chip-remove" aria-label="Remove tag">&times;</button>';
					chip.querySelector('button').addEventListener('click', function(){ tags.splice(i,1); renderTags(); });
					tagChips.appendChild(chip);
				});
				tagsHidden.value = tags.join(',');
				tagCount.textContent = tags.length + ' / 5';
			}
			tagInput.addEventListener('keydown', function(e){
				if((e.key === 'Enter' || e.key === ',') && this.value.trim()){
					e.preventDefault();
					if(tags.length < 5){ tags.push(this.value.trim()); renderTags(); this.value=''; }
				}
			});

			// Submit create form
			document.getElementById('csc-forum-create-form').addEventListener('submit', function(e){
				e.preventDefault();
				var btn = document.getElementById('csc-forum-submit-btn');
				var err = document.getElementById('csc-forum-modal-error');
				btn.disabled = true; btn.textContent = 'Posting…';
				err.setAttribute('hidden','');
				var fd = new FormData(this);
				fd.append('action', 'csc_forum_create');
				fd.append('nonce', nonce);
				fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' })
					.then(function(r){ return r.json(); })
					.then(function(res){
						if(res.success){ window.location = forumUrl + '?forum_topic=' + res.data.id; }
						else { err.textContent = res.data || 'Something went wrong.'; err.removeAttribute('hidden'); btn.disabled=false; btn.textContent='Start Conversation'; }
					})
					.catch(function(){ err.textContent='Network error, please try again.'; err.removeAttribute('hidden'); btn.disabled=false; btn.textContent='Start Conversation'; });
			});

			// Row click
			document.querySelectorAll('.csc-forum-topic').forEach(function(row){
				row.style.cursor = 'pointer';
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * Thread / single topic view
	 * --------------------------------------------------------------------- */
	private function render_thread( $topic_id, $user ) {
		$topic = get_post( $topic_id );
		if ( ! $topic || $topic->post_type !== 'csc_forum_topic' || $topic->post_status !== 'publish' ) {
			return '<p class="csc-error">Topic not found.</p>';
		}

		$forum_url   = Csc_Dashboard::portal_url( 'member-forum' );
		$topic_cats  = wp_get_post_terms( $topic_id, 'csc_forum_cat' );
		$topic_cat   = ! is_wp_error( $topic_cats ) && ! empty( $topic_cats ) ? $topic_cats[0] : null;
		$likes       = (int) get_post_meta( $topic_id, '_csc_forum_likes', true );
		$liked       = in_array( $user->ID, array_map( 'intval', (array) get_post_meta( $topic_id, '_csc_forum_liked_by', false ) ), true );
		$nonce       = wp_create_nonce( 'csc_forum_nonce' );

		// Author
		$author      = get_userdata( $topic->post_author );
		$author_name = $author ? trim( get_user_meta( $author->ID, 'first_name', true ) . ' ' . get_user_meta( $author->ID, 'last_name', true ) ) : '';
		$author_name = $author_name ?: ( $author ? $author->display_name : 'Member' );
		$photo_id    = $author ? get_user_meta( $author->ID, '_csc_profile_photo_id', true ) : '';
		$photo_url   = $photo_id ? wp_get_attachment_image_url( $photo_id, 'thumbnail' ) : '';
		$av_init     = $author_name ? strtoupper( substr( $author_name, 0, 1 ) ) : 'M';
		$time_ago    = human_time_diff( strtotime( $topic->post_date ), current_time( 'timestamp' ) ) . ' ago';

		// Replies (comments)
		$comments = get_comments( array(
			'post_id' => $topic_id,
			'status'  => 'approve',
			'order'   => 'ASC',
		) );

		ob_start();
		?>
		<div class="csc-member-portal">
			<?php echo Csc_Dashboard::render_sidebar( 'forum', $user ); ?>

			<main class="csc-portal-main">

				<!-- Breadcrumb -->
				<nav class="csc-co-back">
					<a href="<?php echo esc_url( $forum_url ); ?>" class="csc-co-back__link">
						<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="13 4 7 10 13 16"/></svg>
						Back to threads
					</a>
				</nav>

				<!-- Topic -->
				<div class="csc-forum-thread">

					<!-- Topic header -->
					<div class="csc-forum-thread-post csc-forum-thread-post--op">
						<div class="csc-forum-thread-post__header">
							<?php if ( $topic_cat ) : ?>
							<span class="csc-forum-topic-cat" style="<?php echo esc_attr( self::cat_pill_style( $topic_cat->term_id ) ); ?>"><?php echo esc_html( $topic_cat->name ); ?></span>
							<?php endif; ?>
							<h1 class="csc-forum-thread-title"><?php echo esc_html( $topic->post_title ); ?></h1>
							<div class="csc-forum-thread-post__meta">
								<span class="csc-forum-topic__avatar">
									<?php if ( $photo_url ) : ?>
									<img src="<?php echo esc_url( $photo_url ); ?>" alt="">
									<?php else : ?>
									<?php echo esc_html( $av_init ); ?>
									<?php endif; ?>
								</span>
								<strong><?php echo esc_html( $author_name ); ?></strong>
								<span class="csc-forum-thread-post__time"><?php echo esc_html( $time_ago ); ?></span>
							</div>
						</div>
						<div class="csc-forum-thread-post__body">
							<?php echo wp_kses_post( wpautop( $topic->post_content ) ); ?>
						</div>
						<div class="csc-forum-thread-post__footer">
							<button class="csc-forum-like-btn <?php echo $liked ? 'is-liked' : ''; ?>"
								data-topic="<?php echo esc_attr( $topic_id ); ?>"
								data-nonce="<?php echo esc_attr( $nonce ); ?>"
								aria-label="Like this topic">
								<svg viewBox="0 0 20 20" fill="<?php echo $liked ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17s-7-4.5-7-9a5 5 0 0 1 7-4.58A5 5 0 0 1 17 8c0 4.5-7 9-7 9z"/></svg>
								<span class="csc-forum-like-count"><?php echo esc_html( $likes ); ?></span>
							</button>
						</div>
					</div>

					<!-- Replies -->
					<?php if ( ! empty( $comments ) ) : ?>
					<div class="csc-forum-replies">
						<?php foreach ( $comments as $comment ) :
							$c_author    = get_userdata( $comment->user_id );
							$c_name      = $c_author ? trim( get_user_meta( $c_author->ID, 'first_name', true ) . ' ' . get_user_meta( $c_author->ID, 'last_name', true ) ) : '';
							$c_name      = $c_name ?: ( $c_author ? $c_author->display_name : 'Member' );
							$c_photo_id  = $c_author ? get_user_meta( $c_author->ID, '_csc_profile_photo_id', true ) : '';
							$c_photo_url = $c_photo_id ? wp_get_attachment_image_url( $c_photo_id, 'thumbnail' ) : '';
							$c_init      = $c_name ? strtoupper( substr( $c_name, 0, 1 ) ) : 'M';
							$c_time      = human_time_diff( strtotime( $comment->comment_date ), current_time( 'timestamp' ) ) . ' ago';
						?>
						<div class="csc-forum-thread-post">
							<div class="csc-forum-thread-post__header">
								<div class="csc-forum-thread-post__meta">
									<span class="csc-forum-topic__avatar">
										<?php if ( $c_photo_url ) : ?>
										<img src="<?php echo esc_url( $c_photo_url ); ?>" alt="">
										<?php else : ?>
										<?php echo esc_html( $c_init ); ?>
										<?php endif; ?>
									</span>
									<strong><?php echo esc_html( $c_name ); ?></strong>
									<span class="csc-forum-thread-post__time"><?php echo esc_html( $c_time ); ?></span>
								</div>
							</div>
							<div class="csc-forum-thread-post__body">
								<?php echo wp_kses_post( wpautop( $comment->comment_content ) ); ?>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<?php endif; ?>

					<!-- Reply box -->
					<div class="csc-forum-reply-box">
						<form id="csc-forum-reply-form">
							<textarea id="csc-forum-reply-input" class="csc-input csc-textarea csc-forum-reply-input"
								rows="3" placeholder="Write a reply…" required></textarea>
							<p class="csc-forum-modal-error" id="csc-forum-reply-error" hidden></p>
							<div class="csc-forum-reply-actions">
								<button type="submit" class="csc-btn-primary csc-forum-reply-submit" id="csc-forum-reply-btn">
									<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="2" y1="10" x2="18" y2="10"/><polyline points="12 4 18 10 12 16"/></svg>
									Post Reply
								</button>
							</div>
						</form>
					</div>

				</div>

			</main>
		</div>

		<script>
		(function(){
			var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var nonce    = <?php echo wp_json_encode( $nonce ); ?>;
			var topicId  = <?php echo (int) $topic_id; ?>;

			// Like button — disabled during request to prevent double-fire
			var likeBtn = document.querySelector('.csc-forum-like-btn');
			if(likeBtn){
				likeBtn.addEventListener('click', function(){
					if(likeBtn.disabled) return;
					likeBtn.disabled = true;
					var fd = new FormData();
					fd.append('action','csc_forum_like');
					fd.append('nonce', nonce);
					fd.append('topic_id', topicId);
					fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
						.then(function(r){return r.json();})
						.then(function(res){
							if(res.success){
								likeBtn.querySelector('.csc-forum-like-count').textContent = res.data.likes;
								likeBtn.classList.toggle('is-liked', res.data.liked);
								likeBtn.querySelector('svg').setAttribute('fill', res.data.liked ? 'currentColor':'none');
							}
						})
						.finally(function(){ likeBtn.disabled = false; });
				});
			}

			// Post reply
			document.getElementById('csc-forum-reply-form').addEventListener('submit', function(e){
				e.preventDefault();
				var btn  = document.getElementById('csc-forum-reply-btn');
				var err  = document.getElementById('csc-forum-reply-error');
				var body = document.getElementById('csc-forum-reply-input').value.trim();
				if(!body) return;
				btn.disabled = true; btn.querySelector('svg').style.display='none';
				err.setAttribute('hidden','');
				var fd = new FormData();
				fd.append('action','csc_forum_reply');
				fd.append('nonce', nonce);
				fd.append('topic_id', topicId);
				fd.append('reply_body', body);
				fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
					.then(function(r){return r.json();})
					.then(function(res){
						if(res.success){ window.location.reload(); }
						else { err.textContent = res.data||'Error posting reply.'; err.removeAttribute('hidden'); btn.disabled=false; }
					});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/* -----------------------------------------------------------------------
	 * AJAX — create topic
	 * --------------------------------------------------------------------- */
	public function ajax_create_topic() {
		check_ajax_referer( 'csc_forum_nonce', 'nonce' );

		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_send_json_error( 'Unauthorised.' );
		}

		$title = sanitize_text_field( $_POST['topic_title'] ?? '' );
		$body  = sanitize_textarea_field( $_POST['topic_body'] ?? '' );
		$cat   = sanitize_key( $_POST['topic_cat'] ?? '' );
		$tags  = sanitize_text_field( $_POST['topic_tags'] ?? '' );

		if ( ! $title || ! $body ) {
			wp_send_json_error( 'Title and body are required.' );
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'csc_forum_topic',
			'post_title'   => $title,
			'post_content' => $body,
			'post_status'  => 'publish',
			'post_author'  => $user->ID,
			'comment_status' => 'open',
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( 'Failed to create topic.' );
		}

		if ( $cat && term_exists( $cat, 'csc_forum_cat' ) ) {
			wp_set_post_terms( $post_id, array( $cat ), 'csc_forum_cat' );
		}

		if ( $tags ) {
			$tag_arr = array_filter( array_map( 'sanitize_text_field', explode( ',', $tags ) ) );
			update_post_meta( $post_id, '_csc_forum_tags', implode( ',', array_slice( $tag_arr, 0, 5 ) ) );
		}

		wp_send_json_success( array( 'id' => $post_id ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX — post reply
	 * --------------------------------------------------------------------- */
	public function ajax_reply() {
		check_ajax_referer( 'csc_forum_nonce', 'nonce' );

		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, '_csc_status', true );
		if ( $status !== 'approved' ) {
			wp_send_json_error( 'Unauthorised.' );
		}

		$topic_id = absint( $_POST['topic_id'] ?? 0 );
		$body     = sanitize_textarea_field( $_POST['reply_body'] ?? '' );

		if ( ! $topic_id || ! $body ) {
			wp_send_json_error( 'Missing data.' );
		}

		$topic = get_post( $topic_id );
		if ( ! $topic || $topic->post_type !== 'csc_forum_topic' ) {
			wp_send_json_error( 'Topic not found.' );
		}

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'  => $topic_id,
			'comment_content'  => $body,
			'user_id'          => $user->ID,
			'comment_author'   => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_approved' => 1,
		) );

		if ( ! $comment_id ) {
			wp_send_json_error( 'Failed to post reply.' );
		}

		wp_send_json_success( array( 'comment_id' => $comment_id ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX — like / unlike topic
	 * --------------------------------------------------------------------- */
	public function ajax_like() {
		check_ajax_referer( 'csc_forum_nonce', 'nonce' );

		$user     = wp_get_current_user();
		$topic_id = absint( $_POST['topic_id'] ?? 0 );

		if ( ! $topic_id ) {
			wp_send_json_error( 'Missing topic.' );
		}

		// Cast to int — meta is stored as string so strict in_array would always miss
		$liked_by = array_map( 'intval', (array) get_post_meta( $topic_id, '_csc_forum_liked_by', false ) );
		$liked    = in_array( $user->ID, $liked_by, true );

		if ( $liked ) {
			delete_post_meta( $topic_id, '_csc_forum_liked_by', $user->ID );
			$likes = max( 0, (int) get_post_meta( $topic_id, '_csc_forum_likes', true ) - 1 );
		} else {
			add_post_meta( $topic_id, '_csc_forum_liked_by', $user->ID );
			$likes = (int) get_post_meta( $topic_id, '_csc_forum_likes', true ) + 1;
		}

		update_post_meta( $topic_id, '_csc_forum_likes', $likes );
		wp_send_json_success( array( 'likes' => $likes, 'liked' => ! $liked ) );
	}
}
