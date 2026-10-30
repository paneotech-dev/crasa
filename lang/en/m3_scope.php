<?php

/*
 * Item 1j: a moderator limited to member states and/or committees.
 */

return [
    'section' => 'Moderation scope',
    'help' => 'For an account that approves content. Tick member states or committees to limit the approval inbox to their items. Leave everything unticked for a moderator who sees every item.',
    'rule' => 'An item belongs to a member state or committee through its own member state or committee field (publications have both, events have a committee). News, pages, social posts and items with neither field are regional: only moderators without a scope see them.',
    'member_states' => 'Member states',
    'committees' => 'Committees',
    'your_scope' => 'Your scope: :scope',
];
