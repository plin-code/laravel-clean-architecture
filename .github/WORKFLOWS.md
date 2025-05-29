# GitHub Workflows Configuration

This project includes multiple workflow options for code style checking. Choose the approach that best fits your team's needs.

## Code Style Workflows

### Option 1: Strict Mode (Current)
**File**: `.github/workflows/code-style.yml`

**Behavior**:
- ❌ Fails CI if code style issues are found
- 🛑 Requires manual fixing before merge
- 📋 Shows exactly what needs to be fixed

**Best for**:
- Open source projects
- Teams focused on code quality education
- Projects where every commit should be intentional

**Usage**: Already active in `ci.yml`

### Option 2: Auto-fix Mode
**File**: `.github/workflows/code-style-fix.yml`

**Behavior**:
- 🤖 Automatically fixes code style issues
- 📝 Commits fixes with "Fix coding style issues with Pint 🤖"
- ✅ Never blocks CI for style issues

**Best for**:
- Private repositories
- Teams prioritizing speed over strict processes
- Projects with trusted contributors only

**Usage**: Uncomment in `ci.yml` and comment strict mode

## How to Switch

### To Auto-fix Mode:

1. Edit `.github/workflows/ci.yml`:
```yaml
jobs:
  tests:
    uses: ./.github/workflows/tests.yml

  # Option 1: Strict mode (fails CI if code style issues found)
  # code-style:
  #   uses: ./.github/workflows/code-style.yml
  
  # Option 2: Auto-fix mode (automatically commits style fixes)
  code-style-fix:
    uses: ./.github/workflows/code-style-fix.yml

  static-analysis:
    uses: ./.github/workflows/static-analysis.yml

  quality-gate:
    runs-on: ubuntu-latest
    needs: [tests, code-style-fix, static-analysis]
    # ... rest of the config
```

2. Optional: Delete `.github/workflows/code-style.yml` if not needed

### To Strict Mode:
Current configuration (no changes needed)

## Recommendation

For this **public package**, we recommend **Strict Mode** because:
- 🎯 Enforces quality standards
- 📚 Educates contributors
- 🔒 Prevents accidental style violations
- 🧹 Keeps git history clean

Consider Auto-fix Mode only for:
- Private team repositories
- Rapid prototyping phases
- Teams with established trust and processes 