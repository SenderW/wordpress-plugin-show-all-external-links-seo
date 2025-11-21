# wordpress-plugin-show-all-external-links-seo
A simple WordPress Plugin that shows all external links
# Show External Links per Post for SEO

A WordPress admin tool that lists every published post and the external links found in its content. Useful for SEO reviews, link audits, guest post checks, and keeping oversight on large sites.

- Author: **Dr. Wolfgang Sender** - [LinkedIn](https://www.linkedin.com/in/absender/) - [Life-in-Germany.de](https://life-in-germany.de/)
- License: **MIT** 

## Features

- Scans all published posts
- Extracts external links only
- Skips posts without external links
- Clickable external links in the admin list
- HTTP status check for each external link
  - 200 green
  - 3xx blue
  - 404 or 410 orange
  - Other 4xx or 5xx red
- Shows each post URL once, then lists its external links line by line
- Empty line after each post block for readability
- In the HTML view, the post URL is a clickable Edit link opening in a new tab
- Optional plain text output with `&format=txt`

## Location

After activation: **Tools → External links per post**  
Plain text: `/wp-admin/tools.php?page=ext-links-per-post&format=txt`

## Why

- Keep outbound links in check for SEO on large sites
- Find broken or redirected targets quickly
- Review and police external linking in guest posts
- Export a quick snapshot for audits

## Install

1. Upload the PHP file to `/wp-content/plugins/`
2. Activate it in **Plugins**
3. Open **Tools → External links per post**

## Notes

- Only published posts are scanned
- Internal and relative links are ignored
- Status is checked with a short timeout and limited redirects
- Text mode prints the status code in square brackets

## Disclaimer

Provided as is with no warranty or guarantees. Use at your own risk. HTTP requests may be slow or blocked by target sites. Always test on staging before production.

## License

MIT
