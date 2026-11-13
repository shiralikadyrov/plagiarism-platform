# Moodle plugin: SDU NLP similarity check (`plagiarism_sduembed`)

Connects Moodle assignments to the project's API server. When a student submits
a file or online text, the plugin compares it with the other submissions to the
same assignment and shows the teacher the share of sentences that have a close
match in meaning, with a report of the matched sentence pairs.

**Status: beta, not yet run on a live Moodle site.** The request, batching and
result-merging code is tested against the real API server, and every PHP file
passes a syntax check. Installation, the settings pages and the grading view
still have to be confirmed on a Moodle test site (roadmap tasks E2 and E3).
Please report what you find.

| | |
|---|---|
| Moodle | 4.1 or later |
| PHP extensions | `curl`, `zip`, `mbstring` (all required by Moodle itself) |
| Needs | A running API server (`server.py` in this repository) that the Moodle server can reach |
| Activities | Assignment: file submissions and online text |
| Licence | GNU GPL v3 or later (required for Moodle plugins). The rest of the repository is MIT. |

## Install

1. Start the API server and note its address and key. See the main
   [README](../../README.md).
2. Copy the folder `sduembed` to `plagiarism/sduembed` inside the Moodle code
   directory (in Moodle 5.1 and later: `public/plagiarism/sduembed`). Or zip the
   `sduembed` folder and upload it under *Site administration → Plugins →
   Install plugins*.
3. Open *Site administration → Notifications* and complete the upgrade.
4. Switch on *Site administration → Advanced features → Enable plagiarism plugins*.
5. Open *Site administration → Plugins → Plagiarism → SDU NLP similarity check*:
   - set **Enable the plugin** to Yes;
   - enter the **API address** and **API key**;
   - select **Save and test the connection**. The page reports the server's
     model and threshold, and whether the key was accepted.
6. To check PDF files, install Poppler (`apt install poppler-utils`) and enter
   the **Path to pdftotext**, usually `/usr/bin/pdftotext`.
7. Make sure Moodle's cron is running. The check runs in the scheduled task
   *Send queued submissions to the similarity API*, every five minutes.

## Use

- **Teacher.** In the assignment settings, open *SDU NLP similarity check* and
  set *Check submissions for similarity* to Yes. After students submit, the
  grading view shows a line such as *Similarity check: 25% of sentences have a
  close match* beside each file or online text. The link opens the report.
- **Student.** The submission page shows a notice that the work will be
  compared. If the administrator allows it, students see their own score and
  which of their sentences were flagged. They never see a classmate's text.

Status messages beside a submission:

| Message | Meaning |
|---|---|
| waiting to be checked | Queued. The scheduled task has not reached it yet. |
| no other submissions to compare with yet | It is the first submission. It is checked again when classmates submit. |
| this file type cannot be read | Supported: `.docx`, `.odt`, `.txt`, `.md`, `.html`, and `.pdf` if pdftotext is set. |
| too short to check | No sentence of three or more words. |
| the check failed | Five attempts failed. The reason is stored in the `errormsg` column of `plagiarism_sduembed_sub`. |
| not checked | Submitted before the check was switched on. The student can save the submission again to queue it. |

## How it works

1. Moodle fires `assessable_uploaded` when a student saves files or online
   text. The plugin stores a queue row. Nothing is sent at that moment, so
   submitting stays fast even if the API is down.
2. The scheduled task takes queued rows, extracts plain text, and gathers the
   latest work of the other students in the same assignment as sources.
3. It posts to `/v1/compare`, at most 50 sources and about one million
   characters per request, and merges the answers. A submission longer than
   200,000 characters is sent in parts.
4. It stores the share of flagged sentences and up to 100 matched sentence
   pairs for each of the 20 closest submissions.
5. Because the API keeps no archive, an early submission cannot be compared
   with later ones at first. The task checks it again once classmates have
   submitted and the *Check again after* time (two hours by default) has passed.

Network errors and server errors are retried after 10, 20, 40 and 80 minutes.
A wrong key or address stops the run and leaves the queue untouched.

## Data and privacy

- **Sent to the API server:** the text of the submission and of the other
  submissions, each labelled with its numeric Moodle submission id. No names,
  usernames, emails or user ids are sent. The reference server does not store
  request texts.
- **Stored in Moodle:** per submitted item, the score, the matched sentence
  pairs, and timestamps (table `plagiarism_sduembed_sub`).
- The plugin implements Moodle's privacy API: results are included in a user's
  data export and removed on a deletion request, including that user's
  sentences quoted in classmates' reports.
- With anonymous marking on, reports do not name the other student until
  identities are revealed.
- Run the API server on infrastructure the institution controls, and tell
  students about the check. The notice on the submission page can be edited in
  the plugin settings.

## Limits to know about

- Comparison is within one assignment only. There is no archive across courses
  or years and no web search. A cross-course archive is roadmap task C6.
- Group submissions are checked as one item per group.
- Only assignments are supported, not forums, workshops or quizzes.
- The score counts correctly quoted and cited text too. It is a prompt for a
  teacher to read the passages, not a finding of misconduct.

## Tests

`tests/text_test.php` covers splitting, batching and merging and runs under
Moodle's PHPUnit:

```bash
vendor/bin/phpunit --testsuite plagiarism_sduembed_testsuite
```

## Uninstall

*Site administration → Plugins → Plugins overview → SDU NLP similarity check →
Uninstall.* This removes both tables and all stored results.
