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
				'body'  => self::blocks(
					array(
						__( 'Start small. A Sprint helps someone reach an outcome through a sequence of actions. Choose something you can demonstrate with three short Steps, such as planning a focused week.', 'sprint-engine' ),
						__( 'Write one sentence in your own notes: “By the end of this Sprint, I will have…” Finish it with a visible result. For example: “a weekly plan with three priorities and time reserved for the first one.”', 'sprint-engine' ),
						__( 'Sketch three actions that lead to that result: list the commitments, choose three priorities, then reserve time for the first priority. Use your own subject if you prefer. Each Step should ask for one manageable piece of work.', 'sprint-engine' ),
						__( 'Before continuing: you have an outcome sentence and three Step titles. Keep this tutorial in this tab. Open your WordPress administration area in a second tab for the next action.', 'sprint-engine' ),
					)
				),
			),
			array(
				'title' => __( 'Create your own Sprint', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::blocks(
					array(
						__( 'In your other tab, open Sprints → Add Sprint. Create a new Sprint with your own title, such as “Plan a focused week”. Leave the supplied Tutorial Sprint as your reference.', 'sprint-engine' ),
						__( 'In the main block editor, add a short introduction: who this Sprint is for, what they will finish with and what they need before starting. This introduction appears before Start Sprint. The first Step will contain the first action, so it does not need another welcome.', 'sprint-engine' ),
						__( 'If you add an excerpt, it takes precedence over the introduction on the start screen. You can leave it blank for now. A featured image and estimated duration are optional; neither is required to build a useful Sprint.', 'sprint-engine' ),
						__( 'Choose Save draft. Sprint structure becomes available after the first save. If you cannot see the setup panels, open the editor’s Options menu and check its panel preferences, or scroll below the content.', 'sprint-engine' ),
						__( 'Before continuing: your separate Sprint is saved as a draft with a clear introduction. Return to this tutorial tab and choose Complete & Continue.', 'sprint-engine' ),
					)
				),
			),
			array(
				'title' => __( 'Add and order your Steps', 'sprint-engine' ),
				'stage' => __( 'Build', 'sprint-engine' ),
				'body'  => self::blocks(
					array(
						__( 'In your saved Sprint editor, find Sprint structure. Enter your first Step title and choose Add Step. Repeat for the other two titles from your plan. Quick Add creates draft Steps attached to this Sprint.', 'sprint-engine' ),
						__( 'Arrange the Steps into the order someone should follow. Use the drag handles or the Move Step up and Move Step down controls, then choose Save order. Save any pending order changes before adding or trashing another Step.', 'sprint-engine' ),
						__( 'The first row is Start and the last is Final. Sprint Engine calculates the positions and next-Step links together; you do not need to enter IDs or wire the links yourself.', 'sprint-engine' ),
						__( 'Choose the first Step’s Edit link. Each Step has its own native WordPress block editor. Back to Sprint returns you to the parent editor after saving. Sprints → Sprint Steps also lets you find Steps and filter them by their parent Sprint.', 'sprint-engine' ),
						__( 'Before continuing: your Sprint has three Steps in a saved order, and you have opened the first Step for editing.', 'sprint-engine' ),
					)
				),
			),
			array(
				'title' => __( 'Write content people can act on', 'sprint-engine' ),
				'stage' => __( 'Author', 'sprint-engine' ),
				'body'  => self::blocks(
					array(
						__( 'In the first Step editor, use Paragraph, Heading and List blocks to explain the action. Give enough context to begin, a small set of instructions and a clear “you are done when…” result. Keep headings descriptive and use a logical heading order.', 'sprint-engine' ),
						__( 'For the weekly-plan example, ask the participant to list this week’s commitments in their own notes, separate fixed appointments from flexible work and finish with one usable list. Core does not collect those notes or provide Workspace or Workbook fields.', 'sprint-engine' ),
						__( 'In the Step settings, optionally set Stage Label to a short phase such as “Plan”, and estimated minutes to a positive whole number. Choose Task mode for an action-focused Step, or Content for reading. Neither mode completes a Step automatically.', 'sprint-engine' ),
						__( 'Use native Image or Audio blocks when they help. Supply useful image alternative text and a written equivalent for essential audio instructions. Media playback does not mark the Step complete. No third-party block or service is needed.', 'sprint-engine' ),
						__( 'Save your work. Use View → Preview Step, or Options → Preview Step if View is unavailable, to inspect the presentation. Save metadata and images before previewing. Then use Back to Sprint and write the remaining Steps.', 'sprint-engine' ),
						__( 'Before continuing: each Step explains a concrete action and its finish condition. The tutorial cannot inspect or validate your work; review it yourself before moving on.', 'sprint-engine' ),
					)
				),
			),
			array(
				'title' => __( 'Publish a ready, Launchable Sprint', 'sprint-engine' ),
				'stage' => __( 'Prepare', 'sprint-engine' ),
				'body'  => self::blocks(
					array(
						__( 'Open each of your Steps, review its content and choose Publish. Draft, Pending, Private and Scheduled Steps prevent member access. Keep the Sprint and its Steps passwordless for the Runner.', 'sprint-engine' ),
						__( 'Return to the Sprint editor. Review Sprint structure: all three Steps should show Published, with the intended Start, Final and next-Step links. Save order if you have changed the sequence. Resolve any setup warnings before proceeding.', 'sprint-engine' ),
						__( 'In Completion screen, you may add a short message that acknowledges the result. An optional next-action button needs both its label and a full HTTP or HTTPS destination. Leave both empty if there is no useful next action.', 'sprint-engine' ),
						__( 'Enable Launchable in the Sprint settings and save or publish the Sprint. The Sprint itself must be Published, every Step Published and its saved linear structure valid. If Launchable is rejected, read the notice, fix the problem and save again.', 'sprint-engine' ),
						__( 'Publishing your own Sprint does not give it this tutorial’s administrator-only marker. Standalone Core allows signed-in WordPress users to access ready Sprints; installed access extensions can apply their own rules. Use a test site while learning if your site already has members.', 'sprint-engine' ),
						__( 'Before continuing: your new Sprint is Published and Launchable with no readiness warning. Locate its Runner URL in the Sprint editor.', 'sprint-engine' ),
					)
				),
			),
			array(
				'title' => __( 'Test the journey and make it yours', 'sprint-engine' ),
				'stage' => __( 'Test', 'sprint-engine' ),
				'body'  => self::blocks(
					array(
						__( 'Open your new Sprint’s Runner URL in another tab while signed in. If the URL is unavailable, check the editor’s Runner status and Settings → Permalinks; the Runner requires pretty permalinks. Review the introduction, then choose Start Sprint.', 'sprint-engine' ),
						__( 'Do the first action as a participant. Viewing the page, scrolling or playing audio does not finish it. Choose Complete & Continue explicitly when done. Leave the Runner tab, open My Sprints at /sprint-engine/dashboard/ and resume your new Sprint to check the saved position.', 'sprint-engine' ),
						__( 'Work through the remaining Steps and complete the final one. Check the completion message. Restart Sprint creates a new attempt while retaining the previous attempt’s progress. Try a narrow screen and keyboard navigation as well as your usual browser.', 'sprint-engine' ),
						__( 'If you change your Steps, save and retest their sequence and finish conditions. A signed-in test member can help you check your own Sprint’s normal access policy; that member cannot run this administrator-only tutorial.', 'sprint-engine' ),
						__( 'You now have a separate Sprint you built yourself. You can reopen this example from Sprints → Settings → Open Tutorial Sprint, inspect its Sprint and Step editors, or edit or delete it whenever you choose. Plugin updates do not replace your tutorial edits.', 'sprint-engine' ),
						__( 'Before completing this tutorial: you have tested start, explicit completion, leave and resume, and the final screen in your own Sprint. Choose Complete Sprint here to save this tutorial’s completion too.', 'sprint-engine' ),
					)
				),
			),
		);
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
