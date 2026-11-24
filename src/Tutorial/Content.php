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
				__( 'Build your own first Sprint while following this working example. You will choose an outcome, create a different Sprint, add useful Steps and test it as a participant.', 'sprint-engine' ),
				__( 'Keep this tutorial open in one tab and WordPress admin in another. Build a separate Sprint there; you do not need to change this tutorial. Allow about 30 minutes, or leave and return when you are ready.', 'sprint-engine' ),
				__( 'Complete & Continue saves your place in this tutorial. It does not check the work in your other Sprint. Only WordPress administrators can run this example; your own Sprints follow your site’s normal access rules.', 'sprint-engine' ),
			)
		);
	}

	/**
	 * Six concrete actions, using controls available in standalone Core.
	 *
	 * @return array
	 */
	public static function steps() {
		return array(
			array(
				'title' => __( 'Choose one useful outcome', 'sprint-engine' ),
				'stage' => __( 'Plan', 'sprint-engine' ),
				'body'  => self::step(
					__( 'A useful Sprint turns one result into manageable actions. Plan yours here, then build a separate Sprint while keeping this tutorial open.', 'sprint-engine' ),
					array(
						__( 'Choose a small outcome. In your notes, finish: “By the end of this Sprint, I will have…” For example: “a weekly plan with three priorities and time reserved for the first one.”', 'sprint-engine' ),
						__( 'Write three Step titles that lead to that result. For the weekly plan: list your commitments, choose three priorities, then reserve time for the first priority. Give each Step one manageable action.', 'sprint-engine' ),
						__( 'Open WordPress admin in a second tab. Keep this tutorial here as your guide; you will create a different Sprint there.', 'sprint-engine' ),
					),
					__( 'You have one outcome sentence, three Step titles and WordPress admin open in another tab.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Create your own Sprint', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Give your new Sprint a clear starting point. Its introduction tells people who it is for, what they will achieve and what they need before starting.', 'sprint-engine' ),
					array(
						__( 'In your admin tab, open Sprints → Add Sprint. Give your separate Sprint a title, such as “Plan a focused week”. Leave this Tutorial Sprint as your reference.', 'sprint-engine' ),
						__( 'Write a short introduction in the main block editor. It appears before Start Sprint; the first Step will contain the first action, without another welcome.', 'sprint-engine' ),
						__( 'Leave the excerpt blank for now: adding one replaces the introduction on the start screen. A featured image and estimated duration are optional.', 'sprint-engine' ),
						__( 'Choose Save draft to unlock Sprint structure. If setup panels are hidden, check panel preferences in the editor’s Options menu or scroll below the content.', 'sprint-engine' ),
					),
					__( 'Your separate Sprint is saved as a draft with a clear introduction. Return here and choose Complete & Continue.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Add and order your Steps', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Turn your plan into a clear path. Sprint structure saves the order and calculates the next-Step links for you.', 'sprint-engine' ),
					array(
						__( 'In your saved Sprint editor, find Sprint structure. Enter a title and choose Add Step. Repeat for all three planned titles. Quick Add attaches each new draft Step to your Sprint.', 'sprint-engine' ),
						__( 'Arrange the Steps with the drag handles or Move Step up/down controls, then choose Save order. Save pending order changes before adding or trashing another Step.', 'sprint-engine' ),
						__( 'Check that Start marks the first row and Final the last. Positions and next-Step links are calculated together; you do not need to enter IDs or connect them yourself.', 'sprint-engine' ),
						__( 'Choose the first Step’s Edit link to open its block editor. After saving, Back to Sprint returns to the parent editor. Find and filter Steps by Sprint in Sprints → Sprint Steps.', 'sprint-engine' ),
					),
					__( 'Your Sprint has three Steps in a saved order, and its first Step is open for editing.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Write content people can act on', 'sprint-engine' ),
				'stage' => __( 'Author', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Make every Step easy to act on: explain why it matters, give a small task and describe the finished result. Use this tutorial’s headings and numbered instructions as a model.', 'sprint-engine' ),
					array(
						__( 'Write your first action with Paragraph, Heading and List blocks. Keep headings descriptive and in a logical order. For the weekly plan, ask people to list commitments in their own notes, separate fixed appointments from flexible work and finish with one usable list.', 'sprint-engine' ),
						__( 'Choose Task mode for action or Content for reading. Optionally add a Stage Label such as “Plan” and estimated minutes as a positive whole number. Neither mode completes a Step automatically.', 'sprint-engine' ),
						__( 'Add native Image or Audio blocks only when useful. Provide image alternative text and a written equivalent for essential audio instructions. Playing audio does not complete the Step; no third-party service is needed.', 'sprint-engine' ),
						__( 'Save content, metadata and images, then use View → Preview Step (or Options → Preview Step). Use Back to Sprint and write the remaining Steps.', 'sprint-engine' ),
					),
					__( 'Each Step gives a concrete action and a clear finish condition. Review the work yourself; this tutorial does not inspect or validate it.', 'sprint-engine' ),
					__( 'Ask people to use their own notes when needed. Core does not collect those notes or include Workspace or Workbook fields.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Publish a ready, Launchable Sprint', 'sprint-engine' ),
				'stage' => __( 'Prepare', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Make your Sprint ready for the Runner. Both the Sprint and every Step must be Published, passwordless and connected in a valid saved order.', 'sprint-engine' ),
					array(
						__( 'Review each Step and choose Publish. Draft, Pending, Private and Scheduled Steps prevent member access.', 'sprint-engine' ),
						__( 'Return to Sprint structure. Check all three Steps show Published, with the intended Start, Final and next-Step links. Save order after any sequence change and resolve setup warnings.', 'sprint-engine' ),
						__( 'Optionally add a message in Completion screen. A next-action button needs both a label and a full HTTP or HTTPS URL; leave both empty if there is no useful next action.', 'sprint-engine' ),
						__( 'Enable Launchable and publish your Sprint. If Launchable is rejected, read the notice, fix the problem and save again. Locate the Runner URL in the Sprint editor.', 'sprint-engine' ),
					),
					__( 'Your own Sprint is Published and Launchable with no readiness warning, and you have its Runner URL.', 'sprint-engine' ),
					__( 'Your Sprint does not inherit this tutorial’s administrator-only rule. Standalone Core admits signed-in WordPress users to ready Sprints; access extensions may apply other rules. Learn on a test site if your site already has members.', 'sprint-engine' )
				),
			),
			array(
				'title' => __( 'Test the journey and make it yours', 'sprint-engine' ),
				'stage' => __( 'Test', 'sprint-engine' ),
				'body'  => self::step(
					__( 'Try the journey as a participant. Testing your own Sprint reveals whether each instruction, saved position and completion screen works as intended.', 'sprint-engine' ),
					array(
						__( 'Open your Sprint’s Runner URL in another tab while signed in. Review the introduction and choose Start Sprint. If unavailable, check Runner status and Settings → Permalinks: the Runner needs pretty permalinks.', 'sprint-engine' ),
						__( 'Do the first action and choose Complete & Continue. Viewing, scrolling or audio playback never completes a Step. Leave, open My Sprints at /sprint-engine/dashboard/ and resume to check your saved position.', 'sprint-engine' ),
						__( 'Complete the remaining Steps and review the final screen. Try Restart Sprint: it creates a new attempt and retains previous progress. Test a narrow screen and keyboard navigation too.', 'sprint-engine' ),
						__( 'Save and retest any changes to the sequence or finish conditions. A signed-in test member can check your Sprint’s normal access; they cannot run this administrator-only tutorial.', 'sprint-engine' ),
					),
					__( 'You have tested start, explicit completion, leave/resume and the final screen in your separate Sprint. Choose Complete Sprint here to save this tutorial’s completion too.', 'sprint-engine' ),
					__( 'Keep this example for reference: Sprints → Settings → Open Tutorial Sprint. You can inspect, edit or delete it later; plugin updates do not replace your tutorial edits.', 'sprint-engine' )
				),
			),
		);
	}

	/**
	 * A short purpose, numbered task and observable outcome in native text blocks.
	 *
	 * @param string   $introduction Purpose of the Step.
	 * @param string[] $tasks Ordered actions.
	 * @param string   $outcome Ready-to-continue outcome.
	 * @param string   $note Optional supporting guidance.
	 * @return string
	 */
	private static function step( $introduction, $tasks, $outcome, $note = '' ) {
		$registry = \WP_Block_Type_Registry::get_instance();
		$heading  = block_has_support( $registry->get_registered( 'core/heading' ), 'className', true ) ? ' class="wp-block-heading"' : '';
		$list     = block_has_support( $registry->get_registered( 'core/list' ), 'className', true ) ? ' class="wp-block-list"' : '';
		$items    = array_map(
			static function ( $task ) use ( $registry ) {
				$item = '<li>' . esc_html( $task ) . '</li>';
				// WordPress 6.0 uses inline list items; newer native editors use child blocks.
				return $registry->is_registered( 'core/list-item' ) ? '<!-- wp:list-item -->' . $item . '<!-- /wp:list-item -->' : $item;
			},
			$tasks
		);
		return self::blocks( array( $introduction ) ) . "\n\n" .
			'<!-- wp:heading {"level":3} --><h3' . $heading . '>' . esc_html__( 'Your task', 'sprint-engine' ) . '</h3><!-- /wp:heading -->' . "\n\n" .
			'<!-- wp:list {"ordered":true} --><ol' . $list . '>' . implode( '', $items ) . '</ol><!-- /wp:list -->' . "\n\n" .
			'<!-- wp:heading {"level":3} --><h3' . $heading . '>' . esc_html__( 'You’re ready to continue when…', 'sprint-engine' ) . '</h3><!-- /wp:heading -->' . "\n\n" .
			self::blocks( array( $outcome ) ) . ( $note ? "\n\n" . self::blocks( array( $note ) ) : '' );
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
