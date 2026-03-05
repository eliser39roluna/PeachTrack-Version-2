#!/usr/bin/env bash
set -euo pipefail
PHP_BIN="/opt/homebrew/opt/php/bin/php"
OUT=$($PHP_BIN /Users/sumitniveriya/clawd/peachtrack-shift-tip-dashboard/scripts/test_payroll_csv.php 2>/dev/null || true)
# If payroll.php uses exit, capture output via php -r include technique:
OUT=$($PHP_BIN -r '$_GET=["export"=>"csv","range"=>"week","mode"=>"all"]; session_start(); $_SESSION["loggedin"]=true; $_SESSION["role"]=101; $_SESSION["id"]=10000; $_SESSION["name"]="System Admin"; require "/Users/sumitniveriya/clawd/peachtrack-shift-tip-dashboard/src/payroll.php";' 2>/dev/null)
echo "$OUT" | head -n 5
# fail if HTML detected in first 5 lines
if echo "$OUT" | head -n 5 | grep -qi "<html\|<!doctype\|<div\|<head"; then
  echo "HTML_DETECTED" >&2
  exit 2
fi
