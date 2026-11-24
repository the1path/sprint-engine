<?php
/**
 * Original, bundled Gutenberg tutorial. No external assets or services.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Tutorial;

defined( 'ABSPATH' ) || exit;

/** Supplies the initial copy only; installed posts remain independently editable. */
final class Content {

	/**
	 * Sprint introduction.
	 *
	 * @return string
	 */
	public static function overview() {
		return self::blocks(
			array(
				__( 'Welcome! In this tutorial you\'ll turn one simple idea into a working Sprint. You\'ll plan an outcome, create your Sprint in WordPress, add three Steps, publish it and experience it yourself.', 'sprint-engine' ),
				__( 'Keep this tutorial open in one browser tab as you work in WordPress admin in another. Take your time — your place is saved when you complete each tutorial Step, so you can return later.', 'sprint-engine' ),
				__( 'This tutorial is available only to WordPress administrators. The Sprint you build is your own and uses the usual access settings for your site.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Tutorial-only plain text for the normal Core completion presentation.
	 *
	 * @return string
	 */
	public static function completion() {
		return implode(
			"\n\n",
			array(
				__( 'You\'ve built your first Sprint — congratulations!', 'sprint-engine' ),
				__( 'You started with a goal, turned it into three practical Steps, published your Sprint and experienced the finished journey. Your Sprint is ready for you to keep developing and share with the people it\'s intended for.', 'sprint-engine' ),
				__( 'Keep this tutorial as a reference for as long as it helps, or remove it when you\'re ready.', 'sprint-engine' ),
				__( 'Coming soon: Sprint Engine Pro will add more ways to capture participants\' responses, build Workbooks and create richer journeys.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Guide one person from an idea to experiencing their own published Sprint.
	 *
	 * @return array
	 */
	public static function steps() {
		return array(
			array(
				'title' => __( 'Decide what your Sprint will achieve', 'sprint-engine' ),
				'stage' => __( 'Plan', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Every good Sprint leads to a clear result. Start by choosing what someone should be able to do or create by the end.', 'sprint-engine' ),
					array(
						__( 'Write down the words “By the end of this Sprint, I will have…” and finish the sentence. For example: “a plan for next week with three clear priorities.”', 'sprint-engine' ),
						__( 'Think of three small actions that lead to that result. For our example: list what needs doing, choose three priorities, and make time for the first priority. Write down a short Step title for each action.', 'sprint-engine' ),
						/* translators: %s: WordPress administration link with an explicit new-tab label. */
						sprintf( __( 'Keep this tutorial open. Select %s to open your site\'s administration area.', 'sprint-engine' ), self::link( admin_url(), __( 'Open WordPress admin (opens in a new tab)', 'sprint-engine' ) ) ),
					),
					__( 'You have one clear outcome and three Step titles, with WordPress admin open in another tab.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Create your Sprint and its Start screen', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Your Start screen introduces your Sprint before someone selects Start Sprint. Let\'s create it now.', 'sprint-engine' ),
					array(
						/* translators: %s: direct Add New Sprint link with an explicit new-tab label. */
						sprintf( __( 'In your admin tab, go to Sprints → Add New Sprint (Add Sprint in some WordPress versions), or select %s here.', 'sprint-engine' ), self::link( admin_url( 'post-new.php?post_type=sprint_engine_sprint' ), __( 'Create a new Sprint (opens in a new tab)', 'sprint-engine' ) ) ),
						__( 'Close WordPress\'s welcome guide if it appears, then give your new Sprint a title. In the main block editor directly beneath the title, write a short introduction: who it\'s for, what they\'ll achieve and anything they need to get started. You can add other useful blocks here, such as a video or audio, if they help.', 'sprint-engine' ),
						__( 'Find the panels below the editor. If Meta Boxes is shown and closed, expand it first. Open Sprint setup to see the Start screen guidance, and explore the optional settings. Sprint structure is where you\'ll add Steps; its Completion screen area is where you\'ll choose what happens at the end. We\'ll return to those as we build. If a panel is collapsed, expand its heading.', 'sprint-engine' ),
						__( 'Optional: in the editor’s Sprint settings sidebar, select Set featured image (open Featured image first if it is collapsed). A wide image around 1600 × 700 px (16:7) is a useful starting point. Keep the important part centred because the Runner crops differently on smaller screens.', 'sprint-engine' ),
						__( 'Choose Save draft. The Runner URL panel below the editor is where you\'ll find your Sprint\'s link once it\'s published. You can customise the Slug later if you wish.', 'sprint-engine' ),
					),
					__( 'Your new Sprint is saved as a draft with a title and Start screen introduction.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Add and arrange your three Steps', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Your Sprint needs a clear path. The order you choose here is the order people will follow.', 'sprint-engine' ),
					array(
						__( 'Return to your new Sprint editor. Below the editor, open Sprint structure, expanding Meta Boxes first if it is shown. Expand any collapsed section.', 'sprint-engine' ),
						__( 'Under New Step title, enter the first title you planned and select Add Step. Repeat for the other two titles. You now have three draft Steps attached to your Sprint.', 'sprint-engine' ),
						__( 'If needed, drag the Steps into the order you want and select Save order.', 'sprint-engine' ),
						__( 'Select Edit beside your first Step. Leave its editor open — that\'s where you\'ll start the next part.', 'sprint-engine' ),
					),
					__( 'Your three draft Steps appear in the right order, and you\'re looking at the editor for Step 1.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Write and publish your Steps', 'sprint-engine' ),
				'stage' => __( 'Author', 'sprint-engine' ),
				'body'  => self::step(
					__( 'You\'re now editing the first Step. Each Step should make one action easy to understand and complete.', 'sprint-engine' ),
					array(
						__( 'Under your Step title, write a short explanation of what to do. If Meta Boxes covers the writing area, collapse it while you write. Use a heading or a short numbered list if helpful, and finish with a clear description of what “done” looks like. Use the three actions you planned in Step 1.', 'sprint-engine' ),
						__( 'Below the editor, expand Meta Boxes if it is shown and find Step mode. Choose Task when the Step asks someone to take action, or Content when it\'s mainly something to read. Stage label and Estimated minutes are optional.', 'sprint-engine' ),
						__( 'When the first Step is ready, choose Publish and confirm if prompted. Then select ← Back to Sprint to return to your main Sprint editor.', 'sprint-engine' ),
						__( 'Open the next Step from Sprint structure. Write and publish it too, then repeat for the final Step.', 'sprint-engine' ),
					),
					__( 'All three Steps contain useful instructions and have been published.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Finish the setup and launch your Sprint', 'sprint-engine' ),
				'stage' => __( 'Prepare', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Your Steps are ready. Now choose what people see at the end and make your Sprint available to run.', 'sprint-engine' ),
					array(
						__( 'Return to your Sprint editor and expand Meta Boxes if it is shown. In Sprint structure, check that all three Steps show as published and appear in the order you want.', 'sprint-engine' ),
						__( 'Find Completion screen within Sprint structure. Write a short Completion message that recognises what someone has achieved — for example: “You\'ve created your weekly plan. You\'re ready to put it into action!”', 'sprint-engine' ),
						__( 'If you\'d like to offer a next action, add both a CTA button label and a CTA URL. For example, link to your website or another helpful page. This button is optional; leave both fields blank if you don\'t need it.', 'sprint-engine' ),
						__( 'Check Launchable and Publish your Sprint, confirming if prompted. If WordPress shows a warning, follow the message and save again once it\'s resolved.', 'sprint-engine' ),
						__( 'After publishing, reload the editor. In Runner URL, review the automatically generated link. You can change the Slug if you\'d like a different address — save and reload to update the link. When everything is ready, the View Runner button will be available.', 'sprint-engine' ),
					),
					__( 'Your Sprint is Published and Launchable, and you can select View Runner.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Experience the Sprint you\'ve built', 'sprint-engine' ),
				'stage' => __( 'Experience', 'sprint-engine' ),
				'body'  => self::step(
					__( 'You\'ve built your first Sprint! Now go through it yourself and see how your content guides someone from beginning to end.', 'sprint-engine' ),
					array(
						__( 'From your Sprint editor, select View Runner. The Runner is the focused, step-by-step experience people use to follow your Sprint. Read the Start screen you created and select Start Sprint.', 'sprint-engine' ),
						__( 'Follow the instructions in your first Step. When you\'re done, select Complete & Continue. Continue through the other Steps in the same way.', 'sprint-engine' ),
						/* translators: %s: same-site My Sprints link with an explicit new-tab label. */
						sprintf( __( 'If you need to stop partway through, select Save & Exit at the top of the Runner. You can return through the same Runner URL or %s and carry on where you stopped.', 'sprint-engine' ), self::link( \ThePath\SprintEngine\Dashboard\Routes::url(), __( 'My Sprints (opens in a new tab)', 'sprint-engine' ) ) ),
						__( 'Complete your last Step and read the Completion screen you created. If anything you\'d like to improve comes to mind, you can edit your Sprint later — that\'s part of making it your own.', 'sprint-engine' ),
					),
					__( 'You\'ve run all the way through the Sprint you created and reached its completion screen. Select Complete Sprint on this tutorial Step to finish the tutorial too.', 'sprint-engine' )
				),
			),
		);
	}

	/**
	 * Escape a trusted site URL as a portable, explicitly labelled new-tab link.
	 *
	 * @param string $url URL generated by WordPress or Core Routes.
	 * @param string $label Visible label including the new-tab indication.
	 * @return string
	 */
	private static function link( $url, $label ) {
		return '<a href="' . esc_url( wp_make_link_relative( $url ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
	}

	/**
	 * A short purpose, numbered task and observable outcome in native text blocks.
	 *
	 * @param string   $introduction Purpose of the Step.
	 * @param string[] $tasks Ordered actions with allowlisted links.
	 * @param string   $outcome Ready-to-continue outcome.
	 * @return string
	 */
	private static function step( $introduction, $tasks, $outcome ) {
		$registry = \WP_Block_Type_Registry::get_instance();
		$heading  = block_has_support( $registry->get_registered( 'core/heading' ), 'className', true ) ? ' class="wp-block-heading"' : '';
		$list     = block_has_support( $registry->get_registered( 'core/list' ), 'className', true ) ? ' class="wp-block-list"' : '';
		$items    = array_map(
			static function ( $task ) use ( $registry ) {
				$allowed = array(
					'a' => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				);
				$item    = '<li>' . wp_kses( $task, $allowed ) . '</li>';
				// WordPress 6.0 uses inline list items; newer native editors use child blocks.
				return $registry->is_registered( 'core/list-item' ) ? '<!-- wp:list-item -->' . $item . '<!-- /wp:list-item -->' : $item;
			},
			$tasks
		);
		return self::blocks( array( $introduction ) ) . "\n\n" .
			'<!-- wp:heading {"level":3} --><h3' . $heading . '>' . esc_html__( 'Your task', 'sprint-engine' ) . '</h3><!-- /wp:heading -->' . "\n\n" .
			'<!-- wp:list {"ordered":true} --><ol' . $list . '>' . implode( '', $items ) . '</ol><!-- /wp:list -->' . "\n\n" .
			'<!-- wp:heading {"level":3} --><h3' . $heading . '>' . esc_html__( 'You’re ready to continue when…', 'sprint-engine' ) . '</h3><!-- /wp:heading -->' . "\n\n" .
			self::blocks( array( $outcome ) );
	}

	/**
	 * Valid native paragraph blocks with escaped original copy.
	 *
	 * @param string[] $paragraphs Paragraph text.
	 * @return string
	 */
	private static function blocks( $paragraphs ) {
		return implode(
			"\n\n",
			array_map(
				static function ( $text ) {
					return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
				},
				$paragraphs
			)
		);
	}
}
