rc# Chat Status Indicator Fix

## Issues Identified:
1. Status indicators showing gray instead of green for online users
2. Inconsistent status logic between ChatSidebar and FloatingChatBox
3. Status merging logic not working properly in RealTimeChatManager

## Files to Fix:
- [ ] ChatSidebar.vue - Fix status logic and color mapping
- [ ] FloatingChatBox.vue - Improve online status detection
- [ ] RealTimeChatManager.vue - Fix status merging and propagation

## Expected Results:
- Online users show green status indicator
- Offline users show gray status indicator
- Status updates in real-time across all components
- Consistent status display in both sidebar and chat windows
