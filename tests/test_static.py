"""Dependency-free checks for assets, forms and security policy."""
import unittest
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class DocumentParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.refs = []
        self.forms = []
        self.inline_handlers = []

    def handle_starttag(self, tag, attrs):
        props = dict(attrs)
        for name, _ in attrs:
            if name.startswith("on"):
                self.inline_handlers.append(name)
        if tag in {"script", "link", "a"}:
            key = {"script": "src", "link": "href", "a": "href"}[tag]
            if props.get(key):
                self.refs.append(props[key])
        if tag == "form":
            self.forms.append(props)


class StaticSiteTests(unittest.TestCase):
    def test_local_resources_exist(self):
        for path in [ROOT / "index.html", *ROOT.glob("*/*.html")]:
            with self.subTest(path=path):
                parser = DocumentParser()
                parser.feed(path.read_text(encoding="utf-8"))
                self.assertFalse(parser.inline_handlers)
                for ref in parser.refs:
                    if ref.startswith(("https:", "http:", "#", "mailto:", "data:")):
                        continue
                    target = ROOT / ref.lstrip("/") if ref.startswith("/") else path.parent / ref
                    self.assertTrue(target.is_file(), f"{path}: missing {ref}")

    def test_demo_never_posts_credentials_to_third_party(self):
        parser = DocumentParser()
        parser.feed((ROOT / "CSS/index.html").read_text(encoding="utf-8"))
        self.assertEqual(len(parser.forms), 1)
        form = parser.forms[0]
        self.assertEqual(form.get("method", "").upper(), "POST")
        self.assertFalse(form.get("action", "").startswith("http"))

    def test_nginx_security_headers(self):
        config = (ROOT / "nginx/default.conf").read_text(encoding="utf-8")
        for header in ["Content-Security-Policy", "X-Content-Type-Options",
                       "Referrer-Policy", "X-Frame-Options"]:
            with self.subTest(header=header):
                self.assertIn("add_header " + header, config)
        self.assertIn("form-action 'self'", config)
        self.assertNotIn("form-action 'self' https://", config)


if __name__ == "__main__":
    unittest.main()
