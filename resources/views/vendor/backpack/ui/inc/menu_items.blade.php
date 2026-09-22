{{-- This file is used for menu items by any Backpack v7 theme --}}
<li class="nav-item px-2 pb-2 d-print-none" data-admin-menu-search-container>
    <div class="input-icon">
        <span class="input-icon-addon" aria-hidden="true"><i class="la la-search"></i></span>
        <input
            type="search"
            class="form-control"
            placeholder="Search pages..."
            aria-label="Search pages"
            autocomplete="off"
            data-admin-menu-search
        >
    </div>
</li>
<li class="nav-item px-3 pb-2 text-muted small d-none" data-admin-menu-search-empty>No matching pages</li>
<li class="nav-item"><a class="nav-link" href="{{ backpack_url('dashboard') }}"><i class="la la-home nav-icon"></i> {{ trans('backpack::base.dashboard') }}</a></li>
@php
    $branchAccess = app(\App\Services\BranchAccessService::class);
    $menuUser = backpack_user();
    $hasAccess = fn (\App\PermissionCode $permission) => $menuUser && ($menuUser->can($permission->value) || $branchAccess->accessibleBranchIds($menuUser, $permission)->isNotEmpty());
    $canViewAttendance = $menuUser && ($menuUser->can('attendance.view') || $branchAccess->accessibleBranchIds($menuUser, \App\PermissionCode::AttendanceView)->isNotEmpty());
    $canViewIncome = $hasAccess(\App\PermissionCode::IncomeView);
    $canCaptureOfferings = $hasAccess(\App\PermissionCode::OfferingsCapture) || $hasAccess(\App\PermissionCode::OfferingsVerify);
    $canViewExpenses = $hasAccess(\App\PermissionCode::ExpensesView);
    $canApproveExpenses = $hasAccess(\App\PermissionCode::ExpensesApprove);
    $canTransferAccounts = $hasAccess(\App\PermissionCode::TransfersCreate) || $hasAccess(\App\PermissionCode::TransfersApprove);
    $canViewFinancialReports = $hasAccess(\App\PermissionCode::FinancialReportsView);
    $canViewDepartments = $menuUser && ($menuUser->can('departments.view') || $branchAccess->accessibleBranchIds($menuUser, \App\PermissionCode::DepartmentsView)->isNotEmpty());
    $canViewGroups = $menuUser && ($menuUser->can('groups.view') || $branchAccess->accessibleBranchIds($menuUser, \App\PermissionCode::GroupsView)->isNotEmpty());
    $canViewAssets = $menuUser && ($menuUser->can('assets.view') || $branchAccess->accessibleBranchIds($menuUser, \App\PermissionCode::AssetsView)->isNotEmpty());
    $canViewChurchSettings = $menuUser?->can('church.settings');
    $canViewUsers = $menuUser?->can('users.view');
    $canManageRoles = $menuUser?->can('roles.manage');
    $canViewAccessControl = $canViewUsers || $canManageRoles;
    $canViewZones = $menuUser && ($hasAccess(\App\PermissionCode::BranchesView) || $branchAccess->activeZoneIds($menuUser)->isNotEmpty());
    $canViewSetup = $menuUser?->hasActiveRole('App Administrator') || $menuUser?->hasActiveRole('Church Administrator');
    $canUseAskData = $canViewAttendance || $hasAccess(\App\PermissionCode::MembersView) || $canViewFinancialReports;
@endphp
@if (backpack_user()?->can('attendance.view') || backpack_user()?->can('attendance.capture'))
    <x-backpack::menu-dropdown title="Services & Attendance" icon="la la-calendar-check">
        <x-backpack::menu-dropdown-item title="Services" icon="la la-calendar" :link="backpack_url('services')" />
        <x-backpack::menu-dropdown-item title="Capture Attendance" icon="la la-clipboard-check" :link="backpack_url('attendance/capture')" />
        <x-backpack::menu-dropdown-item title="Attendance Register" icon="la la-list-check" :link="backpack_url('attendance/register')" />
        <x-backpack::menu-dropdown-item title="Attendance Report" icon="la la-chart-bar" :link="backpack_url('reports/attendance')" />
    </x-backpack::menu-dropdown>
@endif
@if ($canViewIncome || $canCaptureOfferings || $canViewExpenses || $canApproveExpenses || $canTransferAccounts || $canViewFinancialReports)
    <x-backpack::menu-dropdown title="Finance" icon="la la-coins">
        @if ($canViewIncome)
            <x-backpack::menu-dropdown-item title="Income" icon="la la-arrow-down" :link="backpack_url('income')" />
        @endif
        @if ($canCaptureOfferings)
            <x-backpack::menu-dropdown-item title="Offerings" icon="la la-hand-holding-usd" :link="backpack_url('offerings')" />
        @endif
        @if ($canViewExpenses)
            <x-backpack::menu-dropdown-item title="Expenses" icon="la la-receipt" :link="backpack_url('expenses')" />
        @endif
        @if ($canApproveExpenses)
            <x-backpack::menu-dropdown-item title="Approval Queue" icon="la la-check-circle" :link="backpack_url('expense-approvals/queue')" />
        @endif
        @if ($canTransferAccounts)
            <x-backpack::menu-dropdown-item title="Account Transfers" icon="la la-exchange-alt" :link="backpack_url('account-transfers')" />
        @endif
        @if ($canViewFinancialReports)
            <x-backpack::menu-dropdown-item title="Pledges" icon="la la-handshake" :link="backpack_url('pledges')" />
            <x-backpack::menu-dropdown-item title="Financial Report" icon="la la-chart-line" :link="backpack_url('reports/finance')" />
        @endif
    </x-backpack::menu-dropdown>
@endif
@if ($canViewDepartments || $canViewGroups)
    <x-backpack::menu-dropdown title="Ministries" icon="la la-sitemap">
        @if ($canViewDepartments)
            <x-backpack::menu-dropdown-item title="Branch Departments" icon="la la-code-branch" :link="backpack_url('branch-departments')" />
            <x-backpack::menu-dropdown-item title="Department Members" icon="la la-user-friends" :link="backpack_url('department-members')" />
        @endif
        @if ($canViewGroups)
            <x-backpack::menu-dropdown-item title="Cells / Groups" icon="la la-users" :link="backpack_url('groups')" />
            <x-backpack::menu-dropdown-item title="Cell / Group Members" icon="la la-user-plus" :link="backpack_url('group-members')" />
        @endif
    </x-backpack::menu-dropdown>
    <x-backpack::menu-item title="Bulk Assignments" icon="la la-tasks" :link="backpack_url('bulk-assignments')" />
@endif
@if (backpack_user()?->can('events.view'))
    <x-backpack::menu-item title="Events" icon="la la-calendar-alt" :link="backpack_url('events')" />
@endif
@if (backpack_user()?->can('broadcasts.view'))
    <x-backpack::menu-item title="Broadcasts" icon="la la-bullhorn" :link="backpack_url('broadcasts/compose')" />
@endif
@if ($canViewAssets)
    <x-backpack::menu-item title="Assets" icon="la la-boxes" :link="backpack_url('assets')" />
@endif
@if ($menuUser?->hasActiveRole('App Administrator'))
    <x-backpack::menu-dropdown title="Files & Logs" icon="la la-folder-open">
        <x-backpack::menu-dropdown-item title="Activity Log" icon="la la-history" :link="backpack_url('activity-logs')" />
        <x-backpack::menu-dropdown-item title="System Audit Logs" icon="la la-user-shield" :link="backpack_url('audit-logs')" />
        <x-backpack::menu-dropdown-item title="Error Logs" icon="la la-exclamation-triangle" :link="backpack_url('error-logs')" />
        <x-backpack::menu-dropdown-item title="File System" icon="la la-folder" :link="backpack_url('file-system')" />
        <x-backpack::menu-dropdown-item title="Backups" icon="la la-database" :link="backpack_url('backups')" />
    </x-backpack::menu-dropdown>
@endif
@if ($canViewZones)
    <x-backpack::menu-item title="Zones" icon="la la-layer-group" :link="backpack_url('zones')" />
    <x-backpack::menu-item title="Branches" icon="la la-code-branch" :link="backpack_url('branches')" />
@endif
@if ($canViewAccessControl)
    <x-backpack::menu-dropdown title="Access Control" icon="la la-user-shield">
        @if ($canViewUsers)
            <x-backpack::menu-dropdown-item title="Users" icon="la la-users" :link="backpack_url('users')" />
        @endif
        @if ($canManageRoles)
            <x-backpack::menu-dropdown-item title="Roles" icon="la la-id-badge" :link="backpack_url('roles')" />
            <x-backpack::menu-dropdown-item title="Role Assignments" icon="la la-user-check" :link="backpack_url('role-assignments')" />
        @endif
    </x-backpack::menu-dropdown>
@endif
@if (backpack_user() && (backpack_user()->can('members.view') || app(\App\Services\BranchAccessService::class)->accessibleBranchIds(backpack_user(), \App\PermissionCode::MembersView)->isNotEmpty()))
    <x-backpack::menu-dropdown title="People" icon="la la-address-book">
        <x-backpack::menu-dropdown-item title="Members" icon="la la-user-friends" :link="backpack_url('members')" />
        <x-backpack::menu-dropdown-item title="Visitors" icon="la la-user-clock" :link="backpack_url('visitors')" />
        <x-backpack::menu-dropdown-item title="Households" icon="la la-home" :link="backpack_url('households')" />
    </x-backpack::menu-dropdown>
@endif
@if ($canViewZones)
    <x-backpack::menu-item title="Zone Leaders" icon="la la-users-cog" :link="backpack_url('zone-leaders')" />
    <x-backpack::menu-item title="Branch Leaders" icon="la la-user-tie" :link="backpack_url('branch-leaders')" />
@endif
@if ($canViewSetup)
    <x-backpack::menu-dropdown title="Setup" icon="la la-cogs">
        @if ($canViewChurchSettings)
            <x-backpack::menu-dropdown-item title="Church Settings" icon="la la-church" :link="backpack_url('church-settings')" />
        @endif
        @if ($canViewAttendance)
            <x-backpack::menu-dropdown-item title="Service Types" icon="la la-calendar-day" :link="backpack_url('service-types')" />
        @endif
        @if ($canViewDepartments)
            <x-backpack::menu-dropdown-item title="Departments" icon="la la-building" :link="backpack_url('departments')" />
            <x-backpack::menu-dropdown-item title="Department Roles" icon="la la-id-badge" :link="backpack_url('department-roles')" />
        @endif
        @if ($menuUser?->can('branches.manage'))
            <x-backpack::menu-dropdown-item title="Leadership Titles" icon="la la-medal" :link="backpack_url('leadership-titles')" />
        @endif
        @if ($menuUser?->can('roles.manage'))
            <x-backpack::menu-dropdown-item title="Household Relationships" icon="la la-people-arrows" :link="backpack_url('household-relationships')" />
        @endif
        @if ($canViewIncome)
            <x-backpack::menu-dropdown-item title="Payment Methods" icon="la la-credit-card" :link="backpack_url('payment-methods')" />
            <x-backpack::menu-dropdown-item title="Giving Types" icon="la la-tags" :link="backpack_url('giving-types')" />
            <x-backpack::menu-dropdown-item title="Funds" icon="la la-piggy-bank" :link="backpack_url('funds')" />
        @endif
        @if ($canViewFinancialReports)
            <x-backpack::menu-dropdown-item title="Financial Accounts" icon="la la-university" :link="backpack_url('financial-accounts')" />
        @endif
        @if ($canViewExpenses)
            <x-backpack::menu-dropdown-item title="Expense Types" icon="la la-list-alt" :link="backpack_url('expense-types')" />
        @endif
        @if ($canViewAssets)
            <x-backpack::menu-dropdown-item title="Asset Types" icon="la la-tags" :link="backpack_url('asset-types')" />
            <x-backpack::menu-dropdown-item title="Asset Conditions" icon="la la-clipboard-check" :link="backpack_url('asset-conditions')" />
            <x-backpack::menu-dropdown-item title="Asset Statuses" icon="la la-toggle-on" :link="backpack_url('asset-statuses')" />
        @endif
    </x-backpack::menu-dropdown>
@endif
@if ($canUseAskData)
    <x-backpack::menu-item title="Ask Data" icon="la la-chart-bar" :link="backpack_url('ask-data')" />
@endif
<x-backpack::menu-item title="Help Center" icon="la la-question-circle" :link="backpack_url('help')" />
@if (session()->has('impersonator_user_id'))
    <li class="nav-item mt-3 px-2" data-admin-menu-search-ignore><form method="POST" action="{{ route('admin.impersonation.stop') }}">@csrf<button class="btn btn-warning w-100"><i class="la la-user-secret"></i> Stop impersonating</button></form></li>
@endif

@pushOnce('after_scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-admin-menu-search]').forEach((searchInput) => {
                const searchContainer = searchInput.closest('[data-admin-menu-search-container]');
                const menu = searchContainer?.parentElement;

                if (!menu) {
                    return;
                }

                const emptyMessage = menu.querySelector(':scope > [data-admin-menu-search-empty]');
                const menuItems = [...menu.children].filter((element) =>
                    element.classList.contains('nav-item')
                    && !element.hasAttribute('data-admin-menu-search-container')
                    && !element.hasAttribute('data-admin-menu-search-empty')
                    && !element.hasAttribute('data-admin-menu-search-ignore')
                );
                const dropdownState = new Map();
                let previousQuery = '';

                const restoreDropdown = (item) => {
                    const toggle = item.querySelector(':scope > .dropdown-toggle');
                    const dropdown = item.querySelector(':scope > .dropdown-menu');
                    const state = dropdownState.get(item);

                    if (!toggle || !dropdown || !state) {
                        return;
                    }

                    toggle.classList.toggle('show', state.toggleOpen);
                    toggle.setAttribute('aria-expanded', state.toggleOpen ? 'true' : 'false');
                    dropdown.classList.toggle('show', state.menuOpen);
                };

                const filterMenu = () => {
                    const query = searchInput.value.trim().toLocaleLowerCase();

                    if (query && !previousQuery) {
                        menuItems.forEach((item) => {
                            dropdownState.set(item, {
                                toggleOpen: item.querySelector(':scope > .dropdown-toggle')?.classList.contains('show') ?? false,
                                menuOpen: item.querySelector(':scope > .dropdown-menu')?.classList.contains('show') ?? false,
                            });
                        });
                    }

                    let matchCount = 0;

                    menuItems.forEach((item) => {
                        const toggle = item.querySelector(':scope > .dropdown-toggle');
                        const dropdown = item.querySelector(':scope > .dropdown-menu');
                        const dropdownLinks = dropdown ? [...dropdown.querySelectorAll('a.dropdown-item')] : [];
                        const parentLabel = toggle?.querySelector('span')?.textContent.trim().toLocaleLowerCase() ?? '';

                        if (!query) {
                            item.hidden = false;
                            dropdownLinks.forEach((link) => { link.hidden = false; });
                            restoreDropdown(item);

                            return;
                        }

                        if (dropdown && toggle) {
                            const parentMatches = parentLabel.includes(query);
                            let childMatches = 0;

                            dropdownLinks.forEach((link) => {
                                const matches = parentMatches || link.textContent.trim().toLocaleLowerCase().includes(query);
                                link.hidden = !matches;
                                childMatches += matches ? 1 : 0;
                            });

                            const matches = parentMatches || childMatches > 0;
                            item.hidden = !matches;
                            dropdown.classList.toggle('show', matches);
                            toggle.classList.toggle('show', matches);
                            toggle.setAttribute('aria-expanded', matches ? 'true' : 'false');
                            matchCount += matches ? 1 : 0;

                            return;
                        }

                        const matches = item.textContent.trim().toLocaleLowerCase().includes(query);
                        item.hidden = !matches;
                        matchCount += matches ? 1 : 0;
                    });

                    emptyMessage?.classList.toggle('d-none', !query || matchCount > 0);

                    if (!query) {
                        dropdownState.clear();
                    }

                    previousQuery = query;
                };

                searchInput.addEventListener('input', filterMenu);
                searchInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        searchInput.value = '';
                        filterMenu();
                        searchInput.blur();
                    }
                });
            });
        });
    </script>
@endPushOnce
