# **OneClickContent Image Detail Generator**

## **Metadata Generator Upgrade Specification**

## **1\. Project Summary**

Upgrade the existing **OneClickContent Image Detail Generator** WordPress plugin into a safe, scalable, reviewable image metadata generator suitable for large production websites.

The plugin currently generates image details using AI. This release should improve the existing functionality rather than turn the plugin into a broader media-management platform.

The upgraded plugin will focus exclusively on generating and managing:

* Alternative text.
* Attachment titles.
* Captions.
* Attachment descriptions.

The plugin should make it safe to process thousands of existing WordPress images without blindly overwriting useful metadata.

Asset usage detection, orphaned media identification, server-log analysis, media cleanup, and asset deletion are explicitly outside the scope of this project.

---

# **2\. Primary Goals**

The upgraded plugin should allow administrators to:

1. Understand the current metadata condition of the media library.
2. See how many images are missing each supported field.
3. Generate metadata only for fields that are empty.
4. Generate suggestions without immediately changing production data.
5. Review AI-generated suggestions before applying them.
6. Safely process large media libraries in batches.
7. Preserve existing human-written metadata by default.
8. Track every change made by the plugin.
9. Restore previous metadata when necessary.
10. Mark intentionally decorative images so they are not repeatedly flagged.
11. Estimate the size and cost of a generation job before starting it.

---

# **3\. Non-Goals**

The following features are not part of this project:

* Detecting where images are used.
* Crawling the public website.
* Finding orphaned media.
* Scanning page HTML for image references.
* Searching custom fields for asset usage.
* Reading Pantheon, Cloudflare, or server logs.
* Detecting direct requests for images or documents.
* Quarantining or deleting media.
* Managing PDFs, Word documents, videos, or other non-image assets.
* Replacing WordPress responsive image behavior.
* Generating new images.
* Writing complete posts or articles.
* Performing broad SEO content analysis.

These may be handled by a separate media usage and asset-governance project.

---

# **4\. Product Principles**

## **4.1 Safe by Default**

The plugin must not overwrite existing metadata unless the administrator explicitly chooses an overwrite mode.

The default behavior should be:

Generate values only for selected fields that are currently empty.

## **4.2 Human-Written Content Takes Priority**

Existing metadata should be assumed intentional unless proven otherwise.

The plugin should never treat an existing value as disposable merely because the AI can generate a different one.

## **4.3 AI Produces Suggestions**

AI-generated metadata should be treated as proposed editorial content.

Administrators should be able to review, edit, approve, reject, or defer generated values.

## **4.4 Every Change Is Reversible**

Before changing metadata, the plugin must record the original value.

Administrators must be able to restore previous values at the field, image, or batch level.

## **4.5 Accessibility Comes First**

Alternative text should primarily support users of assistive technology.

The plugin should avoid keyword stuffing, unnecessary verbosity, and descriptions that do not provide meaningful visual information.

## **4.6 Large Libraries Must Be Processed Safely**

The plugin must support media libraries containing thousands or tens of thousands of images.

Large jobs must not depend on a single browser request or synchronous PHP process.

---

# **5\. Supported Metadata Fields**

The plugin should support the following WordPress attachment fields:

## **5.1 Alternative Text**

Stored using the standard WordPress attachment metadata key:

\_wp\_attachment\_image\_alt

Alternative text should describe the meaningful visual content of an image.

## **5.2 Attachment Title**

Stored as the attachment post title:

post\_title

The title should make the image easier to identify and search for within the WordPress Media Library.

## **5.3 Caption**

Stored as:

post\_excerpt

Captions are visible editorial content and must therefore be generated conservatively.

## **5.4 Description**

Stored as:

post\_content

Descriptions can provide longer administrative or editorial context about the image.

## **5.5 Independent Field Selection**

Administrators must be able to enable or disable generation for each field independently.

Example configuration:

* Alternative text: enabled.
* Attachment title: enabled.
* Caption: disabled.
* Description: disabled.

A batch must not be required to process every supported field.

---

# **6\. Processing Modes**

The plugin must support four clearly separated processing modes.

## **6.1 Fill Missing Fields**

Generate metadata only when the selected field is empty.

This should be the default and recommended mode.

Example:

| Field | Existing Value | Action |
| ----- | ----- | ----- |
| Alternative text | Empty | Generate |
| Title | Sally Smith Headshot | Preserve |
| Caption | Empty | Generate only if captions are enabled |
| Description | Leadership photo | Preserve |

Whitespace-only values should be treated as empty.

Values containing only a filename should not automatically be treated as empty. They may instead be flagged for review.

## **6.2 Suggestion Mode**

Generate proposed values without changing the attachment.

Suggestions should be stored separately and placed into a review queue.

The review interface should show:

| Field | Current Value | Suggested Value | Confidence |
| ----- | ----- | ----- | ----- |
| Alternative text | Sally Smith | Professional headshot of Sally Smith | High |
| Title | IMG\_4382 | Sally Smith Leadership Headshot | High |
| Caption | Empty | Sally Smith, Chief Operating Officer | Medium |

The administrator should be able to approve or reject each field independently.

## **6.3 Overwrite Mode**

Generate replacement values for fields that already contain metadata.

Overwrite mode must:

* Be disabled by default.
* Require explicit activation in plugin settings.
* Display a warning before each overwrite batch.
* Require confirmation immediately before the batch starts.
* Allow field-by-field overwrite selection.
* Record the original value before making a change.
* Exclude low-confidence suggestions from automatic application.
* Never be triggered automatically during upload.

The interface should clearly distinguish between:

* Filling an empty field.
* Replacing an existing field.

## **6.4 Dry Run Mode**

Analyze a proposed batch without modifying attachment metadata.

A dry run should report:

* Total images evaluated.
* Images eligible for processing.
* Images skipped.
* Empty fields that would be filled.
* Existing fields that would be preserved.
* Existing fields that would be overwritten, when overwrite mode is selected.
* Estimated number of AI requests.
* Estimated image or token usage.
* Estimated cost when provider pricing is configured.
* Unsupported files.
* Potential errors.
* Images already processed by the plugin.
* Images intentionally marked decorative.

Dry-run results should be exportable as CSV.

---

# **7\. Preflight Dashboard**

Create a dashboard that summarizes the image metadata condition of the WordPress Media Library.

## **7.1 Dashboard Metrics**

Display:

* Total image attachments.
* Images missing alternative text.
* Images missing titles.
* Images missing captions.
* Images missing descriptions.
* Images complete for all currently enabled fields.
* Images with pending suggestions.
* Images processed previously.
* Images reviewed by a human.
* Images with failed generation attempts.
* Images marked decorative.
* Images currently queued or processing.

## **7.2 Scope Filters**

Allow administrators to filter the dashboard and generation jobs by:

* Upload date.
* Uploader.
* MIME type.
* File extension.
* Image dimensions.
* File size.
* Attachment parent.
* Missing metadata field.
* Processing status.
* Review status.
* AI provider.
* Previously processed.
* Never processed.
* Decorative status.
* Confidence rating.
* Batch ID.

## **7.3 Test Batch Presets**

Provide safe presets for initial testing:

* 10 images.
* 25 images.
* 50 images.
* 100 images.
* Custom amount.

Administrators should be able to choose:

* The oldest eligible images.
* The newest eligible images.
* A random sample.
* Manually selected images.
* Images uploaded by a specific user.
* Images missing a specific field.

---

# **8\. Image Audit Screen**

Create a dedicated WordPress administration screen using familiar WordPress list-table patterns.

Each image row should display:

* Thumbnail.
* Attachment ID.
* Filename.
* Current title.
* Current alternative text.
* Caption status.
* Description status.
* Upload date.
* Uploader.
* Dimensions.
* File size.
* Current processing status.
* Review status.
* Last processed date.
* Last reviewed date.
* Available actions.

## **8.1 Row Actions**

Each image should support:

* Generate missing metadata.
* Generate suggestions.
* Regenerate a selected field.
* Review suggestions.
* View history.
* Restore previous values.
* Mark as decorative.
* Remove decorative status.
* Mark as reviewed.
* Retry failed generation.

## **8.2 Bulk Actions**

Bulk actions should include:

* Generate missing fields.
* Generate suggestions.
* Run a dry run.
* Approve selected suggestions.
* Reject selected suggestions.
* Mark as reviewed.
* Mark as decorative.
* Retry failed items.
* Restore the most recent plugin changes.
* Export selected records.

---

# **9\. AI Provider Support**

## **9.1 Existing Providers**

Preserve and improve support for the providers currently supported by the plugin, including:

* OpenAI.
* Google Gemini.

The provider layer should be abstracted so additional providers can be added later without rewriting the processing system.

Suggested interface:

interface OCC\_IDG\_Provider\_Interface {
	public function validate\_credentials(): bool;

	public function generate\_metadata(
		int $attachment\_id,
		array $requested\_fields,
		array $context
	): array;

	public function estimate\_cost(
		array $attachments,
		array $requested\_fields
	): array;
}

## **9.2 Provider Configuration**

Each provider should support:

* API key.
* Model selection.
* Connection test.
* Request timeout.
* Retry limit.
* Rate-limit settings.
* Maximum response size.
* Optional cost configuration.
* Provider-specific options.

## **9.3 API Key Security**

API keys must:

* Never appear in generated HTML.
* Never be returned through an unprotected REST endpoint.
* Never be written to normal logs.
* Be masked in the administration interface.
* Be available through environment constants.

Suggested constants:

define( 'OCC\_IDG\_OPENAI\_API\_KEY', '...' );
define( 'OCC\_IDG\_GEMINI\_API\_KEY', '...' );

When a key is supplied by a constant, the administration field should indicate that the key is managed externally.

---

# **10\. Generation Context**

The plugin should provide the AI with enough context to produce useful metadata without sending unnecessary content.

Potential context includes:

* Image file itself or provider-supported visual input.
* Filename.
* Existing attachment title.
* Existing alternative text.
* Existing caption.
* Existing description.
* Parent post title.
* Parent post excerpt.
* Parent post type.
* Site name.
* Organization name.
* Configured editorial guidance.
* Configured terminology.
* Nearby image block text when safely available.

## **10.1 Context Rules**

The plugin should:

* Send only the minimum necessary context.
* Avoid sending an entire post when a short excerpt is enough.
* Avoid sending unrelated page content.
* Exclude unpublished context by default.
* Exclude private post context by default.
* Allow developers to filter the context before it is sent.
* Clearly disclose what content may be sent to the selected AI provider.

---

# **11\. Alternative Text Rules**

Alternative text generation is the most important function of the plugin.

Generated alternative text should:

* Describe meaningful visual information.
* Be concise.
* Use natural language.
* Avoid beginning with “image of” or “picture of” unless context requires it.
* Avoid repeating nearby text unnecessarily.
* Avoid repeating an identical caption.
* Avoid keyword stuffing.
* Avoid promotional language unless the image itself contains that message.
* Avoid guessing names.
* Avoid guessing locations.
* Avoid guessing medical conditions.
* Avoid guessing race, ethnicity, gender identity, disability, religion, or other sensitive characteristics.
* Avoid inventing emotional states.
* Avoid unsupported organizational titles.
* Describe visible text when that text is important.
* Recommend an empty alt value when the image appears decorative.

## **11.1 Named People**

Use a person’s name only when reliable context identifies the person.

The visual model recognizing a public figure or believing it recognizes someone is not sufficient by itself.

If reliable identity context is unavailable, use a visual description such as:

Professional headshot of a person wearing a navy blazer.

## **11.2 Text-Heavy Graphics**

For infographics, event graphics, and images containing substantial text:

* Provide a concise description of the graphic’s purpose.
* Do not attempt to place every word from the graphic into the alt field.
* Flag images requiring longer surrounding text or manual review.
* Allow the suggestion to be classified as low confidence.

---

# **12\. Title Rules**

Generated attachment titles should:

* Be human-readable.
* Help administrators find the image in the Media Library.
* Avoid raw camera filenames.
* Avoid UUIDs or meaningless number strings.
* Avoid including file extensions.
* Use configured capitalization rules.
* Remain factual.
* Avoid presenting guessed identities as facts.

Examples:

Sally Smith Leadership Headshot

Centerstone Nashville Office Exterior

Family Talking With Behavioral Health Counselor

Titles do not need to match alternative text exactly.

---

# **13\. Caption Rules**

Captions are visible content and should be handled more conservatively than attachment titles or descriptions.

Generated captions should:

* Be optional.
* Add useful context.
* Avoid repeating the alternative text word-for-word.
* Avoid fabricating people, titles, places, dates, or events.
* Avoid describing decorative stock photography as a real organizational event.
* Be placed into review by default.
* Not be automatically applied unless the administrator explicitly enables automatic caption application.

Recommended default behavior:

Generate caption suggestions, but require human approval before publishing them.

---

# **14\. Description Rules**

Generated attachment descriptions should:

* Be factual.
* Provide useful editorial or administrative context.
* Be more detailed than the title.
* Avoid unsupported assumptions.
* Avoid confidential or sensitive information.
* Avoid repeating the filename.
* Be optional.

Descriptions may include:

* What the image contains.
* The image’s likely editorial purpose.
* Known event or location context.
* Relevant attribution when supplied through trusted metadata.

---

# **15\. Decorative Images**

The plugin must distinguish between a missing alt value and an intentionally empty alt value.

## **15.1 Decorative Statuses**

Each image should support the following states:

* Not reviewed.
* Alternative text required.
* Intentionally decorative.
* Requires manual decision.

## **15.2 Decorative Workflow**

Administrators should be able to:

* Mark an image as decorative.
* Approve an empty alternative-text value.
* Add an optional reason.
* Record the user who made the decision.
* Record the decision date.
* Remove decorative status later.

Once an image is marked decorative, it should not continue appearing in missing-alt warnings unless the administrator includes decorative images in the filter.

## **15.3 AI Decorative Recommendation**

The AI may recommend that an image is decorative, but the AI should not automatically mark it decorative.

The recommendation should require approval.

---

# **16\. Confidence Levels**

Every generated field should include a confidence classification.

Required levels:

* High.
* Medium.
* Low.

Optional internal numeric scores may also be stored.

## **16.1 High Confidence**

Examples:

* The image is visually clear.
* Context strongly identifies the subject.
* The generated description contains no uncertain identities or events.

## **16.2 Medium Confidence**

Examples:

* The general scene is clear but specific context is uncertain.
* A person’s role is inferred from page context.
* The image contains text that may be partially unreadable.

## **16.3 Low Confidence**

Examples:

* Identity is uncertain.
* Text is difficult to read.
* The image could have multiple interpretations.
* The image is an infographic requiring fuller context.
* The image appears sensitive.
* The visual model cannot reliably inspect the image.

Low-confidence output must require manual review.

It should not be eligible for automatic application.

---

# **17\. Suggestion Review Queue**

Create a dedicated review screen for pending suggestions.

## **17.1 Review Layout**

For each image, display:

* Large image preview.
* Filename.
* Current metadata.
* Suggested metadata.
* Confidence for each field.
* Reason for low confidence, when applicable.
* Available contextual information.
* Provider and model used.
* Generation date.
* Processing batch.

## **17.2 Review Actions**

Allow administrators to:

* Accept a single field.
* Accept all fields for one image.
* Edit a field before accepting.
* Reject a single field.
* Reject all fields for one image.
* Keep the current value.
* Defer the image.
* Regenerate a field.
* Mark the image as requiring manual review.
* Mark the image as decorative.
* Move to the next image.

## **17.3 Review Statuses**

Use statuses such as:

* Not generated.
* Queued.
* Processing.
* Suggestion ready.
* Partially approved.
* Approved.
* Rejected.
* Deferred.
* Needs manual review.
* Intentionally decorative.
* Failed.
* Restored.

---

# **18\. Background Processing**

Large generation jobs must use a queue.

Action Scheduler is the preferred implementation unless the existing plugin already has a reliable queue architecture.

## **18.1 Queue Requirements**

The processing system should:

* Break jobs into small batches.
* Process attachments independently.
* Prevent duplicate jobs for the same attachment and field.
* Survive browser closure.
* Resume after interrupted requests.
* Respect API rate limits.
* Retry temporary failures.
* Use exponential backoff where appropriate.
* Stop retrying permanent errors.
* Support pause and cancellation.
* Record item-level errors.
* Avoid failing an entire batch because one image fails.

## **18.2 Attachment Locking**

Use temporary locks to prevent:

* Two batches processing the same field simultaneously.
* Duplicate API charges.
* Race conditions when approving suggestions.
* Overlapping manual and automatic updates.

Locks must expire safely if a job crashes.

## **18.3 Configurable Batch Settings**

Allow configuration of:

* Images per queue action.
* Concurrent requests.
* Delay between requests.
* Retry count.
* Request timeout.
* Daily request ceiling.
* Maximum batch size.

Use conservative defaults.

---

# **19\. Batch Management**

Create a batch-management screen.

Each batch should record:

* Batch ID.
* Batch name.
* Processing mode.
* Fields requested.
* Provider.
* Model.
* Initiating user.
* Start date.
* Completion date.
* Total attachments.
* Completed attachments.
* Skipped attachments.
* Failed attachments.
* Pending attachments.
* Estimated requests.
* Actual requests.
* Estimated cost.
* Actual known cost where available.
* Batch status.

## **19.1 Batch Actions**

Administrators should be able to:

* View progress.
* Pause the batch.
* Resume the batch.
* Cancel remaining work.
* Retry failures.
* Export results.
* View generated suggestions.
* Restore changes made by the batch.

## **19.2 Cancellation Behavior**

Canceling a batch should:

* Stop future queued work.
* Not undo completed changes automatically.
* Preserve completed history.
* Offer a separate restore-batch action when changes were applied.

---

# **20\. Change History**

Every metadata change made through the plugin must be logged.

Record:

* Attachment ID.
* Field.
* Original value.
* New value.
* Generated suggestion.
* Final approved value.
* Provider.
* Model.
* Confidence.
* Generation timestamp.
* Approval timestamp.
* User who initiated generation.
* User who approved the change.
* Processing mode.
* Batch ID.
* Whether the suggestion was edited before approval.
* Prompt version.
* Restoration status.

A custom database table should be used instead of storing complete history in post meta.

Suggested table:

wp\_occ\_idg\_history

---

# **21\. Rollback**

Administrators should be able to restore:

* One field.
* All plugin-modified fields for one image.
* The most recent change.
* A specific historical value.
* All changes from a selected batch.

## **21.1 Rollback Rules**

A rollback must:

* Record the rollback as a new history event.
* Not erase the previous audit record.
* Display which user performed the rollback.
* Warn when the current value has changed since the original plugin update.
* Avoid overwriting newer human edits without explicit confirmation.

---

# **22\. Data Storage**

Use custom tables for operational records that may grow significantly.

Suggested tables:

wp\_occ\_idg\_suggestions
wp\_occ\_idg\_history
wp\_occ\_idg\_batches
wp\_occ\_idg\_batch\_items

## **22.1 Suggestions Table**

Suggested fields:

id
attachment\_id
field\_name
current\_value
suggested\_value
approved\_value
confidence
confidence\_reason
provider
model
prompt\_version
status
batch\_id
generated\_at
reviewed\_at
reviewed\_by

## **22.2 History Table**

Suggested fields:

id
attachment\_id
field\_name
old\_value
new\_value
suggested\_value
provider
model
confidence
action\_type
batch\_id
initiated\_by
approved\_by
created\_at

## **22.3 Batch Table**

Suggested fields:

id
name
mode
provider
model
requested\_fields
status
total\_items
completed\_items
failed\_items
skipped\_items
estimated\_cost
created\_by
created\_at
started\_at
completed\_at

## **22.4 Lightweight Attachment Metadata**

Attachment metadata may be used for current-state flags:

\_occ\_idg\_last\_processed
\_occ\_idg\_last\_reviewed
\_occ\_idg\_review\_status
\_occ\_idg\_decorative
\_occ\_idg\_decorative\_reason
\_occ\_idg\_decorative\_by
\_occ\_idg\_decorative\_at

Do not store large suggestion or history collections in serialized attachment metadata.

---

# **23\. Media Library Integration**

Add lightweight metadata status indicators to the WordPress Media Library.

Possible indicators:

* Missing alternative text.
* Metadata incomplete.
* Suggestion pending.
* AI processed.
* Human reviewed.
* Decorative.
* Failed generation.

Avoid adding too many full-text columns to the default Media Library.

Status indicators should link to the plugin’s detailed audit or review screens.

---

# **24\. Attachment Edit Screen**

Add an **Image Detail Generator** panel to individual attachment-editing screens.

The panel should display:

* Current alternative text.
* Current attachment title.
* Current caption.
* Current description.
* Metadata completeness.
* Decorative status.
* Review status.
* Last generated date.
* Last reviewed date.
* Provider and model previously used.

Available actions:

* Generate all missing enabled fields.
* Generate a selected field.
* Generate suggestions.
* Review pending suggestions.
* Regenerate a field.
* Mark as decorative.
* Remove decorative status.
* View change history.
* Restore a previous value.

---

# **25\. Settings**

## **25.1 General Settings**

Include:

* Enabled metadata fields.
* Default processing mode.
* Default provider.
* Default model.
* Default batch size.
* Maximum permitted batch size.
* Retry count.
* Request timeout.
* Audit-history retention.
* Remove plugin data on uninstall.

Removing plugin data on uninstall should be disabled by default.

## **25.2 Generation Settings**

Include:

* Organization name.
* Site description.
* Editorial tone.
* Preferred terminology.
* Prohibited terminology.
* Title capitalization style.
* Maximum alternative-text length.
* Caption review requirement.
* Description generation behavior.
* Custom prompt instructions.
* Whether unpublished page context may be used.
* Whether private attachment context may be sent.

## **25.3 Safety Settings**

Include:

* Allow overwrite mode.
* Require confirmation for overwrite.
* Require review for captions.
* Require review for low-confidence output.
* Minimum confidence for automatic application.
* Preserve human-written metadata.
* Allow generation automatically on upload.
* Maximum requests per day.
* Maximum estimated batch cost.

Recommended defaults:

* Overwrite mode: disabled.
* Automatic generation on upload: disabled.
* Captions require review: enabled.
* Low-confidence output requires review: enabled.
* Preserve existing metadata: enabled.

---

# **26\. Permissions**

Create custom WordPress capabilities.

Suggested capabilities:

occ\_idg\_view\_dashboard
occ\_idg\_generate\_metadata
occ\_idg\_review\_suggestions
occ\_idg\_overwrite\_metadata
occ\_idg\_restore\_metadata
occ\_idg\_manage\_batches
occ\_idg\_manage\_settings
occ\_idg\_export\_reports

Administrators should receive all capabilities during activation.

Editors may optionally receive:

* Dashboard viewing.
* Suggestion review.
* Metadata generation.
* Report export.

Overwrite, rollback, and settings access should remain more restricted.

---

# **27\. Reports and Exports**

Provide CSV exports for:

* Missing metadata.
* Completed metadata.
* Pending suggestions.
* Approved suggestions.
* Rejected suggestions.
* Failed requests.
* Batch results.
* Change history.
* Decorative images.
* Images requiring manual review.
* Estimated and actual provider usage.

Export fields should include the attachment ID and edit URL so administrators can return to the relevant image.

---

# **28\. WP-CLI Support**

Add WP-CLI commands for large-site management and testing.

Suggested commands:

wp occ-idg preflight
wp occ-idg generate
wp occ-idg suggest
wp occ-idg batches
wp occ-idg retry
wp occ-idg restore
wp occ-idg export

Examples:

wp occ-idg preflight \--field=alt

wp occ-idg generate \\
	\--missing-only \\
	\--fields=alt,title \\
	\--limit=100

wp occ-idg suggest \\
	\--fields=alt,caption \\
	\--attachment\_ids=123,456,789

wp occ-idg restore \--batch=42

Overwrite operations must require an explicit flag:

wp occ-idg generate \\
	\--overwrite \\
	\--fields=title \\
	\--attachment\_ids=123

---

# **29\. Security Requirements**

All administration operations must use:

* WordPress capability checks.
* Nonces.
* Sanitized input.
* Escaped output.
* Prepared SQL statements.
* Strict REST API permission callbacks.
* Server-side validation.
* Safe file and MIME-type checks.

The plugin must not expose:

* Provider API keys.
* Authorization headers.
* Internal file paths.
* Full provider request payloads to unauthorized users.
* Private attachment information.
* Sensitive page context.

Debug logging must be disabled by default.

When enabled, debug logging should redact credentials and authorization headers.

---

# **30\. Privacy Requirements**

The plugin must clearly explain that image data and selected context may be sent to an external AI provider.

The plugin should:

* Send only required information.
* Avoid unpublished content by default.
* Avoid private content by default.
* Avoid sending user data unless necessary.
* Allow developers to filter or redact context.
* Identify the provider and model used.
* Retain enough information for an audit without storing provider secrets.
* Allow all external AI functionality to be disabled.

The plugin must not claim that using an AI API automatically makes processing compliant with HIPAA, GDPR, or another regulatory standard.

---

# **31\. WordPress Compatibility**

The plugin should:

* Use native WordPress APIs.
* Preserve native attachment behavior.
* Work with the block editor.
* Work with the classic Media Library.
* Work with standard attachment edit screens.
* Support common custom post types.
* Be compatible with WordPress multisite where practical.
* Avoid modifying generated image files.
* Avoid modifying `srcset`.
* Avoid changing registered image sizes.
* Avoid regenerating thumbnails.
* Avoid interfering with CDN image transformations.

Relevant APIs include:

get\_post()

wp\_update\_post()

get\_post\_meta()

update\_post\_meta()

wp\_get\_attachment\_metadata()

wp\_get\_attachment\_image\_src()

---

# **32\. Extensibility**

Provide filters and actions for common integration points.

Suggested filters:

apply\_filters(
	'occ\_idg\_generation\_context',
	$context,
	$attachment\_id
);

apply\_filters(
	'occ\_idg\_requested\_fields',
	$fields,
	$attachment\_id
);

apply\_filters(
	'occ\_idg\_generated\_value',
	$value,
	$field,
	$attachment\_id,
	$response
);

apply\_filters(
	'occ\_idg\_confidence\_threshold',
	$threshold,
	$field
);

apply\_filters(
	'occ\_idg\_should\_process\_attachment',
	$should\_process,
	$attachment\_id
);

Suggested actions:

do\_action(
	'occ\_idg\_suggestion\_generated',
	$attachment\_id,
	$field,
	$suggestion\_id
);

do\_action(
	'occ\_idg\_metadata\_updated',
	$attachment\_id,
	$field,
	$old\_value,
	$new\_value
);

do\_action(
	'occ\_idg\_batch\_completed',
	$batch\_id
);

---

# **33\. Recommended Development Phases**

## **Phase 1: Safety Foundation**

Implement:

* Current plugin code audit.
* Provider abstraction.
* Fill-missing-fields mode.
* Field-level selection.
* Dry-run mode.
* Change history.
* Safe rollback.
* Existing-value preservation.
* Improved error handling.

## **Phase 2: Review Workflow**

Implement:

* Suggestion storage.
* Review queue.
* Current-versus-suggested comparison.
* Confidence classifications.
* Edit-before-approval.
* Approve and reject actions.
* Decorative-image workflow.

## **Phase 3: Enterprise Batch Processing**

Implement:

* Background processing.
* Batch creation.
* Progress tracking.
* Pause, resume, and cancellation.
* Rate limiting.
* Cost estimation.
* Retry controls.
* Batch-level rollback.

## **Phase 4: Administration Experience**

Implement:

* Preflight dashboard.
* Audit list table.
* Media Library indicators.
* Attachment edit panel.
* CSV reports.
* WP-CLI commands.
* Custom capabilities.

---

# **34\. Minimum Centerstone-Ready Release**

The first production-ready release for Centerstone should include:

1. Preflight counts.
2. Filtering by missing field.
3. Selection of specific metadata fields.
4. Test batches of 10, 25, 50, or 100 images.
5. Dry-run reporting.
6. Fill-missing-fields mode.
7. Suggestion mode.
8. Existing metadata preserved by default.
9. Background queue processing.
10. Progress and error reporting.
11. Alternative-text confidence ratings.
12. Review queue.
13. Edit-before-approval.
14. Decorative-image status.
15. Change history.
16. Field-level rollback.
17. Batch-level rollback.
18. CSV export.
19. OpenAI support.
20. Gemini support.
21. Secure environment-based API keys.
22. Custom WordPress capabilities.

The first Centerstone run should not use overwrite mode.

The recommended initial workflow is:

1. Run the preflight report.
2. Select approximately 100 representative images.
3. Generate suggestions for missing alternative text and titles.
4. Review the output with content stakeholders.
5. Adjust prompts and rules.
6. Run fill-missing mode on a larger batch.
7. Continue processing in controlled batches.
8. Address existing metadata only through a separate reviewed workflow.

---

# **35\. Acceptance Criteria**

The project is complete when an authorized administrator can:

1. See the total number of image attachments.
2. See how many images are missing each metadata field.
3. Filter images by metadata completeness.
4. Select which fields should be generated.
5. Run a dry run without modifying data.
6. View estimated AI requests and cost.
7. Generate metadata only for empty fields.
8. Generate suggestions without modifying production metadata.
9. Compare current and suggested values.
10. Edit a suggestion before approval.
11. Approve or reject individual fields.
12. Process thousands of images through a resumable queue.
13. Pause, resume, or cancel a batch.
14. Retry failed image requests.
15. View provider and model information for generated content.
16. Review confidence ratings.
17. Require manual approval for low-confidence output.
18. Mark an image as intentionally decorative.
19. Prevent decorative images from appearing as unresolved missing-alt items.
20. View a complete history of plugin-generated changes.
21. Restore a previous field value.
22. Restore all changes made by a selected batch.
23. Export missing metadata, suggestions, failures, and history as CSV.
24. Assign generation and review permissions without giving users full administrator access.
25. Complete all processing without modifying responsive image files, thumbnails, `srcset`, or CDN behavior.

---

# **36\. Product Positioning**

The plugin should be positioned as:

A safe, AI-assisted image metadata and accessibility workflow for WordPress.

The primary value is not simply generating text.

The value is enabling organizations to improve image metadata at scale while preserving editorial control through:

* Preflight analysis.
* Safe batch processing.
* Human review.
* Accessibility-focused generation.
* Confidence indicators.
* Audit history.
* Reversible changes.
* Enterprise permissions.

This keeps the plugin focused on one problem and solves that problem thoroughly.
