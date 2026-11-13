/*
 * Project facts shown on the website.
 *
 * Edit this file to publish the grant number, evaluation results, publications,
 * model links and pilot sites. Nothing else needs to change: the pages read
 * these values and fill the "About the project", "Evaluation results",
 * "Publications" and "Source, licence and citation" sections.
 *
 * Leave a value empty ("" or []) until it is known. Empty values show a
 * "will be added" note on the site. Do not enter estimates or targets here:
 * the Evaluation section presents these numbers as measured results.
 */
window.PROJECT_DATA = {
  // Grant number (IRN) of the project, for example "AP00000000".
  grantNumber: "",

  // DOI of the archived release (Zenodo), without "https://doi.org/".
  doi: "",

  // Published models. Example:
  //   { name: "sdu-nlp/plagiarism-embed-kk-ru-en", url: "https://huggingface.co/...", use: "similarity" }
  models: [],

  // Validation results from task 3.2. Example of a filled-in block:
  //   corpus: "Validation set from partner institutions, 1,200 document pairs",
  //   model: "sdu-nlp/plagiarism-embed-kk-ru-en",
  //   threshold: 0.80,
  //   date: "June 2026",
  //   rows: [
  //     { group: "By discipline",          label: "Humanities", n: 300, precision: 0.91, recall: 0.88, f1: 0.89, accuracy: 0.90 },
  //     { group: "By type of plagiarism",  label: "Paraphrase", n: 250, precision: 0.87, recall: 0.84, f1: 0.85 },
  //   ],
  //   note: "Any remark about how the numbers were obtained."
  // (The numbers in this example are placeholders that show the format.)
  evaluation: {
    corpus: "",
    model: "",
    threshold: "",
    date: "",
    rows: [],
    note: "",
  },

  // Peer-reviewed papers from the project. Example:
  //   { authors: "Kadyrov, S., ...", year: 2026, title: "...", venue: "Journal name, 12(3)", doi: "10.xxxx/xxxxx" }
  publications: [],

  // Institutions where the system runs. Example:
  //   { institution: "SDU University", platform: "Moodle 4.5", since: "December 2026", courses: 1, submissions: 84 }
  pilots: [],
};
