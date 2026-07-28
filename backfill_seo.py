#!/usr/bin/env python3
"""
backfill_seo.py

Bulk-adds {{#seo: description=... }} to Farmpedia chapter pages that don't
already have a WikiSEO tag, by extracting the first substantial paragraph
of the "Background" section (or first real paragraph found) as a meta
description.

USAGE:
    python3 backfill_seo.py                  # dry run, shows what would change
    python3 backfill_seo.py --live            # actually saves edits
    python3 backfill_seo.py --live --limit 20 # only process first 20 pages (testing)

REQUIREMENTS:
    pip install pywikibot --break-system-packages
    (or via a virtualenv - recommended for a production wiki)

SETUP (run once, from the directory you'll run this script in):
    python3 -m pywikibot generate_user_files.py
    - When prompted for site, choose "mediawiki" as the family, then supply
      your API endpoint: https://farmpedia.org/api.php
    - Log in as a bot account (create one at Special:BotPasswords) rather
      than your personal admin account:
        python3 -m pywikibot login

NOTES:
- Only processes pages in the main (article) namespace.
- Skips: Special pages, Talk pages, User pages, and any page whose wikitext
  already contains "{{#seo:" or "<seo" (case-insensitive).
- Skips pages shorter than MIN_SOURCE_LENGTH (likely stubs/redirects).
- Descriptions are truncated to MAX_DESC_LENGTH chars at a word boundary.
- og:image is pulled from the first [[File:...]] / [[Image:...]] found.
- Edits are tagged with a clear bot edit summary for easy review/rollback.
"""

import argparse
import itertools
import re
import sys

import pywikibot
from pywikibot import pagegenerators

MAX_DESC_LENGTH = 155
MIN_SOURCE_LENGTH = 400  # skip near-empty/stub pages
EDIT_SUMMARY = "Add SEO meta description via WikiSEO (bot, automated backfill)"

# Lines/phrases to skip when hunting for the "real" first paragraph -
# these are boilerplate present in most Farmpedia chapters and make poor
# meta descriptions.
SKIP_PATTERNS = [
    r"^suggested citation",
    r"^related video",
    r"^click on the image",
    r"^source:\s",
    r"university of guelph",
    r"^\d+\.\d+\s*-",          # chapter numbering headings, e.g. "1.1 - Gloves..."
    r"^[a-z]+,?\s*\(\d{4}\)",   # reference-list lines like "Smith, T. (2016)"
    r"^\d+\.\s",                 # numbered reference list entries "1. Author..."
]

# A line is treated as an "author byline" (and skipped) if it's short, has no
# terminal punctuation, and looks like "Firstname Lastname, Institution".
def looks_like_byline(chunk: str) -> bool:
    if len(chunk) > 120:
        return False
    if re.search(r"[.!?]\s*\w", chunk):  # has a real sentence break -> not a byline
        return False
    return bool(re.search(r",\s*(University|College|Institute|Ministry)", chunk, re.IGNORECASE))


def strip_wikitext(text: str) -> str:
    """Roughly strip wiki/HTML markup down to plain readable text, preserving
    paragraph boundaries so real content can be separated from boilerplate."""
    # Remove templates like {{DISPLAYTITLE:...}}, {{#seo:...}}
    text = re.sub(r"\{\{.*?\}\}", "", text, flags=re.DOTALL)
    # Remove file/image links entirely (not useful as description text)
    text = re.sub(r"\[\[(File|Image):[^\]]*\]\]", "", text, flags=re.IGNORECASE)
    # Convert [url text] -> text
    text = re.sub(r"\[https?://\S+\s+([^\]]+)\]", r"\1", text)
    # Convert [[Link|text]] -> text, [[Link]] -> Link
    text = re.sub(r"\[\[[^\]|]+\|([^\]]+)\]\]", r"\1", text)
    text = re.sub(r"\[\[([^\]]+)\]\]", r"\1", text)
    # Insert paragraph breaks at block-level tag boundaries BEFORE stripping
    # tags, so e.g. the author byline (<h3>...</h3>) and the actual
    # background paragraph (<p>...</p>) don't get glued into one blob.
    block_tags = r"(?:p|div|h1|h2|h3|h4|h5|h6|li|tr|br)"
    text = re.sub(rf"</{block_tags}\s*>", "\n\n", text, flags=re.IGNORECASE)
    text = re.sub(rf"<{block_tags}\b[^>]*>", "\n\n", text, flags=re.IGNORECASE)
    text = re.sub(r"<br\s*/?>", "\n\n", text, flags=re.IGNORECASE)
    # Strip any remaining inline HTML tags (b, i, span, a, etc.)
    text = re.sub(r"<[^>]+>", " ", text)
    # Remove citation parentheses like (Author, 2016) - keep sentence readable
    text = re.sub(r"\([A-Z][^()]{0,60}?\d{4}[^()]{0,10}?\)", "", text)
    # Collapse horizontal whitespace but keep paragraph breaks (\n\n)
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    text = "\n\n".join(line.strip() for line in text.split("\n\n"))
    return text.strip()


def extract_first_image(text: str) -> str | None:
    match = re.search(r"\[\[(?:File|Image):([^\]|]+)", text, flags=re.IGNORECASE)
    if match:
        filename = match.group(1).strip()
        return filename
    return None


def find_description(plain_text: str) -> str | None:
    """Pick the first sentence-rich paragraph that isn't boilerplate."""
    chunks = plain_text.split("\n\n")
    for chunk in chunks:
        chunk = chunk.strip()
        if len(chunk) < 80:
            continue
        if looks_like_byline(chunk):
            continue
        lower = chunk.lower()
        if any(re.search(pat, lower) for pat in SKIP_PATTERNS):
            continue
        return chunk
    # Fallback: longest paragraph found, even if short, rather than nothing
    candidates = [c.strip() for c in chunks if len(c.strip()) > 40]
    if candidates:
        return max(candidates, key=len)
    return None


def truncate_at_word(text: str, max_len: int) -> str:
    if len(text) <= max_len:
        return text
    truncated = text[:max_len].rsplit(" ", 1)[0]
    return truncated.rstrip(",;:.-") + "..."


def build_seo_template(description: str, image_filename: str | None, site) -> str:
    description_escaped = description.replace("|", "{{!}}").replace("\n", " ")
    lines = ["{{#seo:"]
    lines.append(f"|description={description_escaped}")
    if image_filename:
        # Resolve to a direct file URL via Special:FilePath so WikiSEO's
        # og:image gets a real absolute URL, not a wiki page name.
        image_url = f"{site.base_url('')}/index.php/Special:FilePath/{image_filename.replace(' ', '_')}"
        lines.append(f"|og:image={image_url}")
    lines.append("|og:type=article")
    lines.append("|og:site_name=Farmpedia")
    lines.append("|twitter:card=summary_large_image")
    lines.append("}}")
    return "\n".join(lines)


def already_has_seo(text: str) -> bool:
    return bool(re.search(r"\{\{#seo:|<seo\b", text, flags=re.IGNORECASE))


def insert_seo_block(text: str, seo_block: str) -> str:
    # Insert right after {{DISPLAYTITLE:...}} if present, else at the very top.
    displaytitle_match = re.search(r"\{\{DISPLAYTITLE:[^\}]*\}\}", text, flags=re.IGNORECASE)
    if displaytitle_match:
        insert_pos = displaytitle_match.end()
        return text[:insert_pos] + "\n" + seo_block + text[insert_pos:]
    return seo_block + "\n" + text


def process_page(page: pywikibot.Page, live: bool) -> None:
    if not page.exists():
        return
    if page.isRedirectPage():
        return

    text = page.text

    if already_has_seo(text):
        print(f"[SKIP - has SEO]   {page.title()}")
        return

    if len(text) < MIN_SOURCE_LENGTH:
        print(f"[SKIP - too short] {page.title()}")
        return

    plain = strip_wikitext(text)
    description = find_description(plain)
    if not description:
        print(f"[SKIP - no desc]   {page.title()}")
        return

    description = truncate_at_word(description, MAX_DESC_LENGTH)
    image_filename = extract_first_image(text)
    seo_block = build_seo_template(description, image_filename, page.site)
    new_text = insert_seo_block(text, seo_block)

    print(f"[{'LIVE' if live else 'DRY-RUN'}] {page.title()}")
    print(f"    description: {description}")
    if image_filename:
        print(f"    og:image:    {image_filename}")
    print()

    if live:
        page.text = new_text
        page.save(summary=EDIT_SUMMARY, minor=False)


def main():
    parser = argparse.ArgumentParser(description="Backfill WikiSEO descriptions.")
    parser.add_argument("--live", action="store_true", help="Actually save edits (default is dry-run).")
    parser.add_argument("--limit", type=int, default=None, help="Only process first N pages.")
    args = parser.parse_args()

    site = pywikibot.Site()
    site.login()

    gen = pagegenerators.AllpagesPageGenerator(site=site, namespace=0, filterredir=False)
    if args.limit:
        gen = itertools.islice(gen, args.limit)

    count = 0
    for page in gen:
        try:
            process_page(page, live=args.live)
        except Exception as e:
            print(f"[ERROR] {page.title()}: {e}", file=sys.stderr)
        count += 1

    print(f"\nProcessed {count} pages. {'Edits were saved.' if args.live else 'Dry run only - no edits saved. Re-run with --live to apply.'}")


if __name__ == "__main__":
    main()
