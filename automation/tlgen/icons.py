"""Small vector icons (original line drawings on a 24x24 grid) for page
headers and callout boxes. Inline SVG with stroke="currentColor", so they
print as crisp vector paths in the brand colour of whatever contains them,
with no font or bitmap involved."""

_PATHS = {
    "bulb": '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-3.6 10.8c.7.5 1.1 1.3 1.1 2.1V16h5v-.1c0-.8.4-1.6 1.1-2.1A6 6 0 0 0 12 3z"/>',
    "book": '<path d="M3 5.5C5.6 4 8.6 4 12 6c3.4-2 6.4-2 9-.5V19c-2.6-1.5-5.6-1.5-9 .5-3.4-2-6.4-2-9-.5z"/><path d="M12 6v13.5"/>',
    "alert": '<path d="M12 3.5 2.8 19.5h18.4z"/><path d="M12 10v4.2M12 17h.01"/>',
    "clipboard": '<rect x="5" y="4.5" width="14" height="16.5" rx="2"/><path d="M9 4.5V3h6v1.5M8.5 10h7M8.5 13.5h7M8.5 17h4"/>',
    "pencil": '<path d="M4 20l1.1-4.6L15.6 4.9a2 2 0 0 1 2.8 0l.7.7a2 2 0 0 1 0 2.8L8.6 18.9z"/><path d="M13.8 6.7l3.5 3.5"/>',
    "check": '<circle cx="12" cy="12" r="9"/><path d="M8 12.4l2.8 2.8L16.2 9.6"/>',
    "house": '<path d="M3.5 11 12 4l8.5 7"/><path d="M6 9.4V20h12V9.4M10 20v-5h4v5"/>',
    "star": '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
    "key": '<circle cx="8" cy="15" r="4.5"/><path d="M11.2 11.8 20 3M16.6 6.4l2.4 2.4M14.3 8.7l1.9 1.9"/>',
    "speaker": '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z"/><path d="M15.6 9a4 4 0 0 1 0 6M18.2 6.6a7.6 7.6 0 0 1 0 10.8"/>',
    "cards": '<rect x="3" y="6" width="13" height="15" rx="2"/><path d="M8 3h11a2 2 0 0 1 2 2v12"/>',
}


def svg(name: str) -> str:
    return ('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            f'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{_PATHS[name]}</svg>')


def for_kicker(kicker: str) -> str | None:
    """The header icon for a page, from its kicker text ("Worksheet · Grade 3" -> pencil)."""
    k = kicker.lower()
    for word, name in (("lesson plan", "clipboard"), ("worksheet", "pencil"), ("quiz", "check"), ("assessment rubric", "star"),
                       ("homework", "house"), ("answer key", "key"), ("flashcard", "cards")):
        if word in k:
            return name
    return None
