"""Regression checks for the PHP2Shell attack surface.

Actual PHP version/INI and HTTP behavior are validated independently
by the php-runtime-security.yml workflow (not by this static test alone).
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]


class Php2ShellHardeningTests(unittest.TestCase):
    def test_php_image_is_fixed_version_and_cli_only(self):
        dockerfile = (ROOT / "Dockerfile").read_text(encoding="utf-8")
        match = re.search(r"(?m)^FROM php:(\d+)\.(\d+)\.(\d+)-cli-alpine\s*$", dockerfile)
        self.assertIsNotNone(match, "Only an explicitly patched PHP CLI tag is allowed")
        version = tuple(int(i) for i in match.groups())
        minimum = {(8, 2): 35, (8, 3): 36, (8, 4): 27, (8, 5): 12}
        self.assertIn(version[:2], minimum, "Unknown PHP branch needs a security review")
        self.assertGreaterEqual(version[2], minimum[version[:2]])
        self.assertIn("USER www-data", dockerfile)
        self.assertIn("COPY docker/php-hardening.ini", dockerfile)
        self.assertIn("COPY --chown=www-data:www-data PHP/", dockerfile)
        self.assertNotIn("-apache", dockerfile)
        self.assertNotIn("-fpm", dockerfile)

    def test_php_ini_reduces_attack_surface(self):
        config = (ROOT / "docker/php-hardening.ini").read_text(encoding="utf-8")
        for directive in ("session.upload_progress.enabled", "file_uploads",
                          "expose_php", "display_errors"):
            with self.subTest(directive=directive):
                self.assertRegex(config, rf"(?m)^{re.escape(directive)}\s*=\s*Off\s*$")

    def test_php_compose_isolated_from_http_and_filesystem(self):
        compose = (ROOT / "compose.yaml").read_text(encoding="utf-8").split("\n  web:", 1)[0]
        for config in ("network_mode: none", "read_only: true",
                       "cap_drop:", "- ALL", "no-new-privileges:true",
                       "pids_limit: 64"):
            with self.subTest(config=config):
                self.assertIn(config, compose)
        self.assertNotIn("ports:", compose)
        self.assertNotIn("volumes:", compose)

    def test_static_nginx_denies_php_and_unlisted_files(self):
        config = (ROOT / "nginx/default.conf").read_text(encoding="utf-8")
        enabled = "\n".join(line.split("#", 1)[0] for line in config.splitlines())
        self.assertNotRegex(enabled, r"\b(?:fastcgi_pass|proxy_pass|uwsgi_pass)\b")
        self.assertRegex(config, r"location / \{\s*return 404;\s*\}")
        self.assertIn("PHP/index", config)
        self.assertNotIn("PHP/fibonacci", enabled)
        self.assertIn("limit_except GET", config)
        self.assertIn("autoindex off", config)

    def test_docker_build_context_excludes_other_files(self):
        ignore = (ROOT / ".dockerignore").read_text(encoding="utf-8")
        self.assertIn("!PHP/**", ignore)
        self.assertIn("!docker/php-hardening.ini", ignore)
        self.assertTrue(ignore.startswith("*\n"))


if __name__ == "__main__":
    unittest.main()
