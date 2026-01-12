# GitHub Pages Setup Guide

This guide explains how to set up GitHub Pages for this Jekyll documentation site.

## Prerequisites

- Repository hosted on GitHub
- Admin access to the repository

## Setup Steps

### 1. Enable GitHub Pages

1. Go to your repository on GitHub
2. Click on **Settings** tab
3. In the left sidebar, click on **Pages**
4. Under **Build and deployment**:
   - **Source**: Select "GitHub Actions"
5. Click **Save**

### 2. Configure Repository Permissions

The GitHub Actions workflow needs specific permissions to deploy to GitHub Pages.

1. Go to **Settings** → **Actions** → **General**
2. Scroll down to **Workflow permissions**
3. Select **Read and write permissions**
4. Check **Allow GitHub Actions to create and approve pull requests**
5. Click **Save**

### 3. Verify Configuration

The repository already includes:
- `.github/workflows/docs.yml` - GitHub Actions workflow
- `docs/_config.yml` - Jekyll configuration
- `docs/Gemfile` - Ruby dependencies

### 4. Deploy

Push changes to the `main` branch:

```bash
git add .
git commit -m "docs: setup Jekyll with Just the Docs theme"
git push origin main
```

The GitHub Actions workflow will automatically:
1. Build the Jekyll site
2. Deploy to GitHub Pages

### 5. Check Deployment Status

1. Go to the **Actions** tab in your repository
2. Check the "Deploy Documentation" workflow
3. Wait for the workflow to complete (usually 1-2 minutes)

### 6. Access Your Documentation

Once deployed, your documentation will be available at:

```
https://[username].github.io/[repository-name]/
```

For this repository:
```
https://ahmed-bhs.github.io/hexagonal-maker-bundle/
```

## Workflow Details

The GitHub Actions workflow (`.github/workflows/docs.yml`) is triggered:
- On every push to `main` branch that modifies files in `docs/`
- Manually via the Actions tab

### Workflow Steps

1. **Checkout**: Clones the repository
2. **Setup Ruby**: Installs Ruby and dependencies
3. **Setup Pages**: Configures GitHub Pages
4. **Build with Jekyll**: Builds the static site
5. **Upload artifact**: Uploads the built site
6. **Deploy**: Deploys to GitHub Pages

## Troubleshooting

### Workflow Fails with Permission Error

**Error**: `Error: HttpError: Resource not accessible by integration`

**Solution**: Enable workflow permissions (see Step 2 above)

### Site Not Updating

1. Check the Actions tab for failed workflows
2. Clear browser cache
3. Wait a few minutes for CDN to update

### Ruby/Bundle Errors

The workflow uses:
- Ruby 3.1
- Bundler cache for faster builds

If you get dependency errors, check `docs/Gemfile` is up to date.

### Base URL Issues

The `_config.yml` has:
```yaml
baseurl: "/hexagonal-maker-bundle"
url: "https://ahmed-bhs.github.io"
```

Make sure these match your repository settings.

## Updating the Documentation

### Making Changes

1. Edit markdown files in `docs/`
2. Commit and push to `main` branch
3. Workflow automatically deploys changes

### Testing Locally

See `docs/README.md` for local development instructions.

### Adding New Pages

1. Create `.md` file in appropriate directory
2. Add YAML front matter:
```yaml
---
layout: default
title: Page Title
parent: Parent Page
nav_order: 1
---
```
3. Commit and push

## Configuration Files

### `_config.yml`

Main Jekyll configuration:
- Site metadata
- Theme settings
- Navigation
- Search configuration

### `Gemfile`

Ruby dependencies:
- `github-pages` gem
- `just-the-docs` theme
- Jekyll plugins

### `.github/workflows/docs.yml`

GitHub Actions workflow for automated deployment.

## Resources

- [GitHub Pages Documentation](https://docs.github.com/en/pages)
- [Jekyll Documentation](https://jekyllrb.com/docs/)
- [Just the Docs Theme](https://just-the-docs.github.io/just-the-docs/)

## Support

For issues with:
- **Jekyll/Theme**: Check [Just the Docs issues](https://github.com/just-the-docs/just-the-docs/issues)
- **GitHub Pages**: Check [GitHub Pages status](https://www.githubstatus.com/)
- **This documentation**: Open an issue in this repository
