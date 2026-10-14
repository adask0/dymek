=== Dymek – FAQ Chat ===
Contributors: adask0
Tags: chat, faq, chatbot, customer support, help
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight FAQ chat with ready-made answer paths. Runs entirely on your site: no account, no external services, no tracking.

== Description ==

Dymek adds a small chat button to your site. Visitors pick their question from buttons and get your answer straight away, without waiting for a reply and without you sitting at a live chat.

* Answers arranged as paths: each answer can lead to further questions
* Link answers that send visitors to your returns, shipping or contact page
* Import and export your answers as CSV, so you can write them in Excel
* Lightweight: no jQuery, the answers load only when the chat is opened
* Accessible: keyboard navigation, screen reader announcements, Esc closes the chat, text colour picked for contrast
* Your colour and position (bottom left or right)

= Privacy =

Dymek does not connect to any external service, does not use cookies and does not collect or share any data about your visitors. Everything stays on your site.

== Installation ==

1. Install and activate the plugin.
2. Go to Dymek > Answers and add the start answer, or import a CSV file in Dymek > Import / export.
3. Pick the colour and position in Dymek > Settings.

== Frequently Asked Questions ==

= What does the CSV file look like? =

The first row holds the column names: id;parent_id;type;label;content;keywords;url. Each further row is one answer. Leave parent_id empty for the start answer and put the id of the previous answer for the others. The type is "message" or "link"; a link also needs a url. Both ";" and "," separators work, and so does Excel's "CSV UTF-8" format.

= Does it use AI? =

No. Visitors only see the answers you wrote, so the chat never makes up anything about your shop.

= What happens to my answers when I delete the plugin? =

They are deleted together with the settings. To keep them, tick "Keep answers and settings when the plugin is deleted" in Dymek > Settings before you delete the plugin.

== Changelog ==

= 0.1.0 =
* First release.
