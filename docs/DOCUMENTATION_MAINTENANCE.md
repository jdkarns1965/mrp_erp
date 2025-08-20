# Documentation Maintenance System

## Purpose
Ensures all user documentation remains accurate and up-to-date with system changes.

## Documentation Files to Maintain
1. **USER_GUIDE.md** - Comprehensive user manual
2. **QUICK_REFERENCE.md** - Printable quick reference card
3. **CLAUDE.md User Operations Manual** - Integrated guidance
4. **includes/help-system.php** - In-app contextual help

## Maintenance Triggers

### **Immediate Update Required**
- ✅ New page/feature added
- ✅ Navigation structure changes
- ✅ Status codes or workflows modified
- ✅ Database schema changes affecting user experience
- ✅ User interface changes

### **Documentation Review Schedule**
- **After each feature completion** - Update relevant sections
- **Phase completion** - Comprehensive documentation review
- **Monthly** - Accuracy check against codebase
- **When users report documentation issues** - Priority fix

### **Automated Review Agent**
Run this agent task when needed:
```
Task: Review MRP/ERP system documentation for accuracy
Agent: general-purpose
Trigger: After major changes or monthly
```

## Documentation Update Checklist

### When Adding New Features:
- [ ] Update file structure in System Development Manual
- [ ] Add feature to appropriate USER_GUIDE.md section
- [ ] Update navigation paths in QUICK_REFERENCE.md
- [ ] Add help content to help-system.php
- [ ] Update status codes if changed
- [ ] Test all documented workflows
- [ ] Update version stamps

### When Modifying Existing Features:
- [ ] Review affected USER_GUIDE.md sections
- [ ] Update workflows in QUICK_REFERENCE.md
- [ ] Modify help-system.php tooltips/content
- [ ] Check navigation consistency
- [ ] Verify status codes still accurate
- [ ] Update troubleshooting guides

### When Removing Features:
- [ ] Remove from all documentation files
- [ ] Update navigation references
- [ ] Remove from help system
- [ ] Update workflows that reference removed feature
- [ ] Add to "Known Limitations" if users might expect it

## Quality Assurance Process

### Documentation Testing
1. **Navigation Test**: Follow each documented path in actual system
2. **Workflow Test**: Execute each step-by-step guide
3. **Status Code Test**: Verify all status codes match system
4. **Help System Test**: Check all tooltips and help panels
5. **Quick Reference Test**: Validate all quick paths work

### User Feedback Integration
- Monitor for documentation-related support requests
- Track common user confusion points  
- Update based on real usage patterns
- Incorporate feedback into next review cycle

## Documentation Standards

### Writing Standards
- **User perspective**: Write from user's viewpoint, not technical
- **Action-oriented**: Focus on "how to do" rather than "what is"
- **Consistent terminology**: Use same terms throughout
- **Visual hierarchy**: Use consistent heading structure
- **Mobile-friendly**: Consider tablet/phone users

### Update Standards
- **Version stamps**: Update last-modified dates
- **Change logs**: Document what changed
- **Cross-references**: Update related sections
- **Completeness**: Don't leave partial updates

## File-Specific Guidelines

### **USER_GUIDE.md**
- Complete section rewrite when major feature changes
- Update table of contents if new sections added
- Maintain step-by-step format
- Include troubleshooting for new features
- Keep examples current with system

### **QUICK_REFERENCE.md**
- Update navigation table for any menu changes
- Verify all keyboard shortcuts work
- Update status codes immediately when changed
- Keep workflows concise and accurate
- Test printability after changes

### **help-system.php**
- Add new contexts for new pages
- Update field tooltips when forms change
- Review help panel content for accuracy
- Test all help interactions
- Update workflow guides for new features

### **CLAUDE.md User Operations Manual**
- Update daily workflows when process changes
- Modify color codes/symbols if system changes
- Update troubleshooting with new common issues
- Keep getting help section current

## Version Control Integration

### Git Hooks for Documentation
Consider adding git pre-commit hook reminder:
```bash
echo "📝 Documentation Checklist:"
echo "[ ] Updated relevant user documentation?"
echo "[ ] Tested documented workflows?"
echo "[ ] Updated status codes if changed?"
echo "[ ] Added help content for new features?"
```

### Commit Message Standards
- Include documentation changes in commit messages
- Tag documentation-only commits clearly
- Reference documentation updates in feature commits

## Maintenance History

### Recent Updates (August 2025)
- ✅ Fixed status codes in QUICK_REFERENCE.md (abbreviated → full words)
- ✅ Removed references to non-existent pages (results.php, orders/view.php)
- ✅ Updated MRP navigation paths (Calculate → Run MRP)
- ✅ Fixed inventory operations documentation (adjust → receive/issue)
- ✅ Added missing MPS functionality documentation
- ✅ Established maintenance process and standards
- ✅ **CRITICAL FIXES (Agent-Identified Issues):**
  - Added MPS to main navigation menu
  - Created production order view page (production/view.php)
  - Created MRP results page (mrp/results.php)
  - Fixed MPS database setup issue (planning tables required)
  - Enhanced help system with MPS context

### Ongoing Maintenance Tasks
- [ ] Complete Phase 2 production features in USER_GUIDE.md
- [ ] Add in-app help to all major pages
- [ ] Create video tutorials for complex workflows
- [ ] Develop role-based documentation when authentication added

## Success Metrics
- User support requests about "how to" decrease
- New users can navigate system without assistance
- Documentation accuracy verified through testing
- User feedback indicates documentation helpfulness

This maintenance system ensures documentation evolves with the system and remains a valuable resource for users.