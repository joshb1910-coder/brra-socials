"""Build setup/news/wix-posts.json from Wix blog API responses.

Usage: python3 setup/wix/export.py posts-text.json rich.json
  posts-text.json: list of Wix posts fetched with the CONTENT_TEXT fieldset.
  rich.json: {post id: richContent} for posts fetched with RICH_CONTENT (articles; appointments use plain text).
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
import appts  # noqa: E402
import ricos  # noqa: E402

CATS = {
    '48a95438-80c2-4493-b8de-20a6f55034ba': 'News',
    'd9a5f02b-05c5-47fc-9aeb-45d99b1916ac': 'Partnerships',
    '8fb07c9f-2a33-4c06-9c3b-6fdb89f267bf': 'Exclusive',
    'a3571c06-a5fc-47fe-a570-58fc01549a53': 'Events',
    '9dcfc55e-2847-4e33-bd82-2ca93b6485b8': 'Appointments',
    '03f059e7-a4af-4ba7-98d2-34c83280199d': 'Feature',
    '7dae1431-c2bf-4a32-80d1-54fb81bd8b4a': 'Educational',
}
TAGS = {
    'c4b5eaf1-401d-43c8-b6d4-fc9b33c3954d': 'Referee', '592601bf-a2a5-4573-926c-bd12bdd2d74a': 'Premier League',
    '2f23d3d4-226d-4db8-aad4-202e155fff23': 'Assistant', '7ad46a2a-e115-4b8b-bd8b-fe7ec31278a3': 'Advert',
    'f7eb5eaa-d349-41eb-b5ac-edb8e20f3565': 'Official', 'acee36ea-db54-4435-b77c-874f8ead2d68': 'Website',
    'ca16d46e-ebad-476a-8b34-20bd0195ea14': 'Referee Development', '07def637-9357-4932-b47c-ab76a2ecf9f7': 'Guest Speaker',
    '45ebb084-1de3-43d8-afff-4dc5155cf862': 'Mainwaring', 'bea70704-940b-43ba-bf5c-2072e9ef559e': 'Membership',
    '20125274-e3ee-4ac2-82b6-99daf423c9fc': 'Committee', '2e85f641-acee-4022-a1e7-093387a6ed0c': 'VAR',
    '38b8bebb-442e-48c7-9785-ce8d62b72dce': 'Communication Strategies', '56e8509d-5ea1-47c6-8939-80d9b4d3a5ca': 'AGM',
    'a10f923b-62d2-4bb1-a798-09d8c090b230': 'Technical Area Management', 'de5283b1-23ba-47c9-8baa-23725a092de6': 'Conflict Resolution',
    'e0dabf23-417d-499b-968d-0a26f7606c51': 'Laws of the Game', 'f72fd4c8-c961-40f8-8bee-b753608f7c1d': 'Cup Final',
}


def categories(p):
    if p['title'].upper().startswith('APPOINTMENTS') or '9dcfc55e-2847-4e33-bd82-2ca93b6485b8' in p['categoryIds']:
        return ['Appointments']
    cats = [CATS[c] for c in p['categoryIds'] if c in CATS]
    specific = [c for c in cats if c != 'News']
    return specific or ['News']


def text_paragraphs(text):
    import html
    return ''.join('<p>' + html.escape(b.strip(), quote=False).replace('\n', '<br>') + '</p>\n'
                   for b in text.split('\n\n') if b.strip())


def main(text_path, rich_path):
    posts = json.load(open(text_path))
    rich = json.load(open(rich_path)) if os.path.exists(rich_path) else {}
    out = []
    for p in posts:
        cats = categories(p)
        if p['id'] in rich:
            body = ricos.to_html(rich[p['id']])
        elif cats == ['Appointments']:
            body = appts.to_html(p['contentText'])
        else:
            body = text_paragraphs(p['contentText'])
        img = (p.get('media') or {}).get('wixMedia', {}).get('image', {})
        cover = img.get('url', '') if (p.get('media') or {}).get('displayed', True) else ''
        out.append({
            'wix_id': p['id'],
            'slug': p['slug'],
            'title': p['title'],
            'date_gmt': p['firstPublishedDate'][:19].replace('T', ' '),
            'categories': cats,
            'tags': [TAGS[t] for t in p.get('tagIds', []) if t in TAGS],
            'cover': cover,
            'members_only': bool(p.get('pricingPlanIds')),
            'html': body,
        })
    dest = os.path.join(os.path.dirname(__file__), '..', 'news', 'wix-posts.json')
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    json.dump(out, open(dest, 'w'), ensure_ascii=False, indent=0)
    print(len(out), 'posts written to', os.path.normpath(dest))


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
