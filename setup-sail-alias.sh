#!/bin/bash

# Setup Sail Alias for pneadm-bootstrap project
# This script adds a 'sail' alias to your shell configuration

echo "==================================="
echo "Laravel Sail Alias Setup"
echo "==================================="
echo ""

# Detect shell
SHELL_CONFIG=""
if [ -f "$HOME/.zshrc" ]; then
    SHELL_CONFIG="$HOME/.zshrc"
    SHELL_NAME="zsh"
elif [ -f "$HOME/.bashrc" ]; then
    SHELL_CONFIG="$HOME/.bashrc"
    SHELL_NAME="bash"
else
    echo "❌ Could not detect .bashrc or .zshrc"
    echo "Please manually add this line to your shell config:"
    echo ""
    echo "alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'"
    echo ""
    exit 1
fi

# Check if alias already exists
if grep -q "alias sail=" "$SHELL_CONFIG"; then
    echo "✅ Sail alias already exists in $SHELL_CONFIG"
    echo ""
    echo "Current alias:"
    grep "alias sail=" "$SHELL_CONFIG"
    echo ""
    read -p "Do you want to update it? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Skipping alias setup."
        exit 0
    fi
    # Remove old alias
    sed -i '/alias sail=/d' "$SHELL_CONFIG"
fi

# Add alias
echo "" >> "$SHELL_CONFIG"
echo "# Laravel Sail alias" >> "$SHELL_CONFIG"
echo "alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'" >> "$SHELL_CONFIG"

echo "✅ Sail alias added to $SHELL_CONFIG"
echo ""
echo "To activate the alias in your current session, run:"
echo "  source $SHELL_CONFIG"
echo ""
echo "Or simply close and reopen your terminal."
echo ""
echo "After that, you can use 'sail' instead of './vendor/bin/sail'"
echo ""
echo "Examples:"
echo "  sail up -d"
echo "  sail artisan migrate"
echo "  sail composer install"
echo "  sail npm run dev"
echo ""
echo "==================================="
echo "Setup Complete!"
echo "==================================="

