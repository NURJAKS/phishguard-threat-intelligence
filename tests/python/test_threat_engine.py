"""Unit tests for PhishGuard Python Threat Intelligence Engine."""
import json
from pathlib import Path
from unittest.mock import patch, MagicMock
import pytest
import sys

SCRIPTS_DIR = Path(__file__).resolve().parent.parent.parent / "python_scripts"
sys.path.insert(0, str(SCRIPTS_DIR))

import domain_info


def test_official_sources_json_validity():
    """Verify official sources database exists and is valid JSON."""
    sources_path = SCRIPTS_DIR / "official_sources.json"
    assert sources_path.exists(), "official_sources.json must exist"
    data = json.loads(sources_path.read_text(encoding="utf-8"))
    assert isinstance(data, (dict, list))


def test_rules_json_validity():
    """Verify security heuristic rules exist and are valid JSON."""
    rules_path = SCRIPTS_DIR / "rules.json"
    assert rules_path.exists(), "rules.json must exist"
    data = json.loads(rules_path.read_text(encoding="utf-8"))
    assert isinstance(data, (dict, list))


def test_tls_info_graceful_failure():
    """Verify TLS inspector gracefully handles non-existent or invalid domains."""
    res = domain_info.get_tls_info("invalid-non-existent-domain-xyz.local")
    assert isinstance(res, dict)
    assert res.get("valid") is False
    assert "error" in res


def test_dns_info_returns_expected_structure():
    """Verify DNS collector returns standard telemetry schema."""
    with patch("dns.resolver.resolve") as mock_resolve:
        mock_rr = MagicMock()
        mock_rr.address = "93.184.216.34"
        mock_resolve.return_value = [mock_rr]

        data = domain_info.get_dns_info("example.com")
        assert "a_records" in data
        assert "mx_records" in data
        assert "ns_records" in data
