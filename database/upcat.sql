UPDATE categories
SET
  long_description = '...',
  faq = '...',
  guide = '...',
  video_url = 'https://www.youtube.com/embed/...'
WHERE slug = 'genshin-impact'; -- repeat per slug (mlbb-diamonds, pubg-uc, etc.)
