#!/usr/bin/env python3
"""
PhishGuard Full Audit - Standalone script for Laravel integration
Accepts URL as command line argument and returns JSON result
"""
import sys
import json
import os
import logging

# Setup logging to stderr (so PHP can capture it)
logging.basicConfig(
    level=logging.WARNING,  # Only warnings and errors
    format='%(levelname)s: %(message)s',
    stream=sys.stderr
)

# Add current directory to path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    from ai_analyzer import analyze_url_full_audit
except ImportError as e:
    print(json.dumps({"error": f"Failed to import ai_analyzer: {e}"}), file=sys.stderr)
    sys.exit(1)

def main():
    if len(sys.argv) < 2:
        result = {"error": "URL argument required"}
        print(json.dumps(result))
        sys.exit(1)
    
    url = sys.argv[1]
    
    # Get OpenRouter API key from environment or arguments
    api_key = os.getenv('OPENROUTER_API_KEY', '')
    if not api_key:
        # Try to get from second argument
        if len(sys.argv) >= 3:
            api_key = sys.argv[2]
        else:
            result = {"error": "OPENROUTER_API_KEY not provided"}
            print(json.dumps(result))
            sys.exit(1)
    
    try:
        # Set OpenRouter API key in environment (analyze_url_full_audit reads it from env)
        os.environ['OPENROUTER_API_KEY'] = api_key
        
        # Perform full audit
        result = analyze_url_full_audit(url)
        
        # Output result as JSON
        print(json.dumps(result, ensure_ascii=False))
        
    except Exception as e:
        result = {"error": str(e)}
        print(json.dumps(result), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()

