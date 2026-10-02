=== LLM Auto Redirect ===
Contributors: Gemini
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Uses an LLM to suggest intelligent redirects for 404 errors found by the Redirection plugin.

== Description ==

This plugin is a powerful assistant for website managers who deal with a large number of 404 "Not Found" errors. It integrates with the popular "Redirection" plugin by John Godley to fetch a list of recent 404 errors.

Instead of manually deciding where to redirect each broken link, this plugin adds a "Suggest" button that sends the broken URL to a Large Language Model (like Google's Gemini). The AI analyzes the URL and, based on a list of your site's main pages, suggests the most logical and contextually relevant page to redirect to.

For example, a 404 for /events/community-fair-2022/ would likely result in a suggestion to redirect to your main /events/ page. A completely random or irrelevant 404 would default to the homepage.

Once you're happy with the suggestion, a "Create" button automatically adds the 301 redirect to the "Redirection" plugin and clears the original 404 log entry.

== Installation ==

IMPORTANT: You must have the "Redirection" plugin by John Godley installed and activated.

Create a folder named llm-auto-redirect in your /wp-content/plugins/ directory.

Place the llm-auto-redirect.php, admin-page-view.php, and readme.txt files into this folder.

Activate the "LLM Auto Redirect" plugin through the 'Plugins' menu in WordPress.

Go to Tools -> LLM Auto Redirect.

Get an API key for Google AI's Gemini Pro model.

Enter your API key into the settings field and click "Save Changes".

You can now start using the suggestion feature!
