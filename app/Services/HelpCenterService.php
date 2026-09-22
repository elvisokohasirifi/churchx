<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class HelpCenterService
{
    /** @var array<string, bool> */
    private array $permissionCache = [];

    /** @var list<string> */
    private array $roleNames = [];

    public function __construct(private readonly BranchAccessService $branchAccess) {}

    /** @return list<array<string, mixed>> */
    public function sections(User $user): array
    {
        $this->roleNames = $user->roleAssignments()
            ->with('role:id,name')
            ->where('is_active', true)
            ->get()
            ->pluck('role.name')
            ->filter()
            ->when($user->hasActiveZoneLeadership(), fn ($roles) => $roles->push('Zone Leader'))
            ->unique()
            ->values()
            ->all();

        return collect($this->articles())
            ->filter(fn (array $article): bool => $this->articleIsVisible($user, $article))
            ->map(fn (array $article): array => $this->prepareArticle($user, $article))
            ->groupBy('category')
            ->map(fn ($articles, string $category): array => [
                'title' => $category,
                'articles' => $articles->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function activeRoleNames(): array
    {
        return $this->roleNames;
    }

    /** @return list<array<string, mixed>> */
    private function articles(): array
    {
        return [
            $this->article(
                'Getting started',
                'Dashboard and navigation',
                'The dashboard summarizes the areas your role can access. The sidebar search finds pages by name, while list-page search filters records.',
                [],
                [],
                [],
                null,
                [
                    $this->task('Sign in with Google', ['On the sign-in page, select Continue with Google.', 'Choose the Google account whose verified email matches your existing ChurchX account.', 'Complete Google authentication. Unknown or inactive accounts are not created or signed in.']),
                    $this->task('Find a page', ['Use the Search pages box at the top of the sidebar.', 'Enter part of a page name, such as Members or Income.', 'Select the matching page. Press Escape to clear the search.']),
                    $this->task('Search a listing', ['Open the required listing page.', 'Use the search box above the table.', 'Enter a name, reference, date, or other visible value.', 'Clear the search to restore all accessible records.']),
                    $this->task('Understand branch scope', ['Church-wide roles see all permitted branches.', 'Branch-scoped roles only see records and dropdown options from assigned branches.', 'Contact an App Administrator if an expected branch is missing.']),
                ],
            ),
            $this->article(
                'Getting started',
                'Ask Data analytics',
                'Ask Data translates plain-language questions into permission-aware attendance, membership, and finance analysis with charts and supporting tables.',
                [],
                ['question', 'from', 'to'],
                ['question'],
                'ask-data',
                [
                    $this->task('Ask a data question', ['Open Ask Data.', 'Enter a question or choose an example.', 'Optionally select a date range.', 'Select Ask and review the written answer, chart, and table.']),
                ],
                ['attendance.view', 'members.view', 'financial_reports.view'],
            ),
            $this->article(
                'Church structure',
                'Church and branches',
                'Church stores the organization-wide identity and defaults. A branch represents a church location or congregation and may optionally belong to a zone. Branch records can include multiple active leaders.',
                [
                    'Church' => 'The single organization record containing the church name, contact details, address, currency, and timezone.',
                    'Branch' => 'A church location with its own code, optional zone, address, status, members, leaders, services, and scoped operational records.',
                ],
                ['zone_id', 'name', 'code', 'address', 'location', 'gps_coordinates', 'date_started', 'status', 'leaders'],
                ['name', 'code', 'status'],
                'branches',
                [
                    $this->task('View branches', ['Open Branches.', 'Search by name or code.', 'Open a row to see its details and leaders.']),
                    $this->task('Add a branch', ['Open Branches and select Add branch.', 'Optionally choose its zone.', 'Enter the required identity fields.', 'Optionally add one or more branch leaders.', 'Save the branch.'], 'branches.manage'),
                    $this->task('Import branches from CSV', ['Open Branches and select Import CSV.', 'Download the sample CSV and retain its column headings.', 'Enter a unique code for every branch.', 'Upload the completed file. No branches are created if any row fails validation.'], 'branches.manage'),
                    $this->task('Edit branch leaders', ['Edit the branch.', 'Add, change, or remove leader rows.', 'Use active dates to preserve leadership history.', 'Save the branch.'], 'branches.manage'),
                ],
                ['branches.view'],
            ),
            $this->article(
                'Church structure',
                'Zones and zone leadership',
                'Zone groups branches under an optional level of oversight. An active ZoneLeader appointment automatically gives its user read-only access to branch and member information throughout that zone.',
                [
                    'Zone' => 'An optional grouping of branches with a unique code and active state.',
                    'ZoneLeader' => 'A dated appointment connecting an application user, zone, and leadership title.',
                ],
                ['name', 'code', 'description', 'is_active', 'zone_id', 'user_id', 'leadership_title_id', 'start_date', 'end_date'],
                ['name', 'code', 'is_active', 'zone_id', 'user_id', 'leadership_title_id', 'start_date'],
                'zones',
                [
                    $this->task('Create a zone', ['Open Zones and select Add zone.', 'Enter a unique name and code.', 'Save the zone, then assign branches to it from each branch form.'], 'branches.manage'),
                    $this->task('Appoint a zone leader', ['Open Zone Leaders and select Add.', 'Choose the zone, active application user, and leadership title.', 'Set the appointment dates and active state.', 'Save. The user receives read-only access to branches in that zone.'], 'branches.manage'),
                    $this->task('End zone access', ['Edit the Zone Leader appointment.', 'Set the end date or turn off Is active.', 'Save; zone-derived access ends immediately.'], 'branches.manage'),
                ],
                ['branches.view'],
            ),
            $this->article(
                'People',
                'Members and branch membership',
                'Member is a person in the church directory who must have an assigned branch leader and may separately have a shepherd for personal pastoral oversight. MemberBranch records a person’s branch history and identifies the current primary branch.',
                [
                    'Member' => 'A church member profile containing identity, contact, demographic, membership, required branch-leader assignment, and optional shepherd information.',
                    'MemberBranch' => 'The historical assignment of a member to a branch, including dates, primary status, and transfer state.',
                ],
                ['primary_branch_id', 'membership_number', 'branch_leader_id', 'shepherd_id', 'first_name', 'middle_name', 'last_name', 'phone', 'alternative_phone', 'email', 'address', 'date_of_birth', 'gender', 'marital_status', 'occupation', 'highest_education', 'date_joined', 'membership_status', 'notes'],
                ['primary_branch_id' => 'Required when creating a member', 'branch_leader_id' => 'Required when the branch has an active leader', 'first_name', 'last_name', 'membership_status'],
                'members',
                [
                    $this->task('Add a member', ['Open People → Members and select Add member.', 'Choose the primary branch and an active leader from that branch.', 'Optionally choose a different member as the shepherd.', 'Enter the required name and membership status.', 'Add contact and demographic details where available.', 'Save the member.'], 'members.create'),
                    $this->task('Import members from CSV', ['Open People → Members and select Import CSV.', 'Download the sample CSV and retain its column headings.', 'Use branch codes to assign primary branches; leave branch_leader_membership_number blank to use the branch’s first active leader.', 'Use membership numbers to identify shepherds.', 'Upload the completed file. No members are created if any row fails validation.'], 'members.create'),
                    $this->task('Bulk assign branch leaders', ['Open Bulk Assignments.', 'Choose Members to branch leader.', 'Select the branch and active branch leader.', 'Select one or more members and submit.'], 'members.update'),
                    $this->task('Bulk assign shepherds', ['Open Bulk Assignments.', 'Choose Members to shepherd.', 'Select the branch and shepherd, or choose Clear shepherd assignment.', 'Select one or more members and submit.'], 'members.update'),
                    $this->task('Update a member', ['Find the member in People → Members.', 'Select Edit, update the profile, and save.'], 'members.update'),
                    $this->task('Transfer a member', ['Open the member record and choose Transfer.', 'Select the destination branch, its new branch leader, and the transfer date.', 'Confirm the transfer; the previous branch assignment is retained as history.'], 'members.update'),
                ],
                ['members.view'],
            ),
            $this->article(
                'People',
                'Visitors and conversion',
                'Visitor stores a guest’s contact and first-visit information. VisitorAttendance links a visitor to a service. Conversion creates a member while retaining the visitor history.',
                [
                    'Visitor' => 'A guest who attended or contacted a branch but has not yet been recorded as a member.',
                    'VisitorAttendance' => 'A record that a particular visitor attended a particular service.',
                ],
                ['name', 'phone', 'email', 'address', 'gender', 'date_of_birth', 'invited_by_member_id', 'branch_id', 'first_visit_date', 'notes'],
                ['name', 'branch_id', 'first_visit_date'],
                'visitors',
                [
                    $this->task('Add a visitor', ['Open People → Visitors and select Add visitor.', 'Enter the visitor name, branch, and first visit date.', 'Add contact and invitation information if known.', 'Save the visitor.'], 'visitors.create'),
                    $this->task('Convert a visitor to a member', ['Open the visitor record and choose Convert.', 'Complete the required member names, join date, status, and branch.', 'Confirm conversion. The new member is linked back to the visitor.'], 'members.create'),
                ],
                ['visitors.view'],
            ),
            $this->article(
                'People',
                'Households',
                'Household groups related members at one address. HouseholdMember records each person’s relationship and whether they are the household head. HouseholdRelationship defines available relationship labels.',
                [
                    'Household' => 'A family or residential unit used to organize related members.',
                    'HouseholdMember' => 'A member’s assignment to a household with relationship and head-of-household status.',
                    'HouseholdRelationship' => 'A reusable relationship label such as Spouse, Child, Parent, or Guardian.',
                ],
                ['family_name', 'address', 'household_id', 'member_ids', 'relationship_id', 'is_head'],
                ['family_name', 'household_id', 'member_ids', 'relationship_id'],
                'households',
                [
                    $this->task('Create a household', ['Open People → Households and select Add household.', 'Enter the family name and optional address.', 'Save the household.'], 'members.update'),
                    $this->task('Bulk assign household members', ['Open Bulk Assignments.', 'Choose Household members.', 'Select the household, relationship, and members.', 'Submit the assignment.'], 'members.update'),
                ],
                ['members.view'],
            ),
            $this->article(
                'Church structure',
                'Branch leadership',
                'BranchLeader assigns a member and leadership title to a branch for a dated period. A branch can have multiple leaders. Branch leadership is separate from the personal shepherd recorded on an individual member.',
                [
                    'BranchLeader' => 'A dated leadership appointment connecting a member, branch, and title.',
                    'LeadershipTitle' => 'A reusable church leadership office or designation.',
                ],
                ['branch_id', 'member_id', 'leadership_title_id', 'start_date', 'end_date', 'is_active'],
                ['branch_id', 'member_id', 'leadership_title_id', 'start_date', 'is_active'],
                'branch-leaders',
                [
                    $this->task('Assign a branch leader', ['Open Branch Leaders and select Add.', 'Choose a branch, a member from that branch, and a leadership title.', 'Set the start date and active state.', 'Save the appointment.'], 'branches.manage'),
                    $this->task('End an appointment', ['Edit the branch leader record.', 'Set the end date and turn off Is active.', 'Save the record to retain leadership history.'], 'branches.manage'),
                ],
                ['branches.view'],
            ),
            $this->article(
                'Services and attendance',
                'Services and service types',
                'Service is a scheduled worship service or gathering. ServiceBranch attaches branches to joint or church-wide services. ServiceType defines reusable service categories.',
                [
                    'Service' => 'A dated gathering with scope, type, location, times, and operational status.',
                    'ServiceBranch' => 'The link between a joint or church-wide service and each participating branch.',
                    'ServiceType' => 'A reusable category such as Sunday Service, Midweek Service, or Prayer Meeting.',
                ],
                ['branch_id', 'scope', 'name', 'service_type', 'date', 'start_time', 'end_time', 'location', 'status'],
                ['scope', 'name', 'service_type', 'date', 'status', 'branch_id' => 'Required when scope is Branch'],
                'services',
                [
                    $this->task('Schedule a service', ['Open Services & Attendance → Services and select Add.', 'Choose Branch, Joint, or Church-wide scope.', 'Select a service type and date; the date defaults to the next Sunday.', 'Confirm location, time, and status, then save.'], 'attendance.capture'),
                    $this->task('Update service status', ['Edit the service.', 'Choose Upcoming, Ongoing, Cancelled, or Completed.', 'Save the service.'], 'attendance.capture'),
                ],
                ['attendance.view'],
            ),
            $this->article(
                'Services and attendance',
                'Attendance capture and register',
                'AttendanceSummary stores aggregate male, female, child, member, and visitor totals for a branch service. MemberAttendance and VisitorAttendance store individual attendance.',
                [
                    'AttendanceSummary' => 'Branch-level totals captured for one service.',
                    'MemberAttendance' => 'An individual member’s present or absent record for a service.',
                    'VisitorAttendance' => 'An individual visitor’s attendance link to a service.',
                ],
                ['service_id', 'branch_id', 'total_male', 'total_female', 'total_children', 'total_members', 'total_visitors', 'attendance'],
                ['service_id', 'branch_id', 'total_male', 'total_female', 'total_children', 'total_members', 'total_visitors'],
                'attendance/capture',
                [
                    $this->task('Capture attendance totals', ['Open Capture Attendance.', 'Choose the branch and service.', 'Enter every attendance total, using zero where applicable.', 'Save the summary.'], 'attendance.capture'),
                    $this->task('Complete the attendance register', ['Open Attendance Register.', 'Choose a branch and service.', 'Mark every listed member Present or Absent.', 'Submit the register. The summary is updated from the register.'], 'attendance.capture'),
                    $this->task('Review attendance reports', ['Open Attendance Report.', 'Review captured summaries for accessible branches.', 'Use Ask Data for trends and branch or leader comparisons.']),
                ],
                ['attendance.view'],
            ),
            $this->article(
                'Ministries',
                'Departments and department members',
                'Department defines a church-wide ministry. BranchDepartment enables it at a branch. DepartmentRole defines positions, and BranchDepartmentMember assigns members to the branch ministry.',
                [
                    'Department' => 'A reusable ministry definition such as Media or Children’s Ministry.',
                    'BranchDepartment' => 'A department enabled at one branch.',
                    'DepartmentRole' => 'A reusable position held within a department.',
                    'BranchDepartmentMember' => 'A dated member assignment to a branch department and role.',
                ],
                ['name', 'description', 'branch_id', 'department_id', 'branch_department_id', 'member_id', 'department_role_id', 'joined_date', 'left_date', 'is_active'],
                ['name', 'branch_id', 'department_id', 'branch_department_id', 'member_id', 'department_role_id'],
                'branch-departments',
                [
                    $this->task('Enable departments for a branch', ['Open Bulk Assignments.', 'Choose Branch departments.', 'Select the branch and one or more departments.', 'Submit; duplicate branch-department combinations are ignored or rejected.'], 'departments.manage'),
                    $this->task('Assign department members', ['Open Bulk Assignments.', 'Choose Department members.', 'Select the branch department and role.', 'Select one or more members from the same branch and submit.'], 'departments.manage'),
                ],
                ['departments.view'],
            ),
            $this->article(
                'Ministries',
                'Cells and groups',
                'ChurchGroup represents a cell, ministry group, fellowship, committee, or other group. GroupMember records member participation and group role.',
                [
                    'ChurchGroup' => 'A branch-linked cell or group with a name, type, and description.',
                    'GroupMember' => 'A dated assignment of a member to a group, optionally with a group-specific role.',
                ],
                ['name', 'type', 'branch_id', 'description', 'group_id', 'member_id', 'joined_date', 'left_date', 'role', 'is_active'],
                ['name', 'type', 'branch_id', 'group_id', 'member_id'],
                'groups',
                [
                    $this->task('Create a cell or group', ['Open Ministries → Cells / Groups and select Add.', 'Choose the group type and branch.', 'Enter its name and optional description.', 'Save the group.'], 'groups.manage'),
                    $this->task('Bulk assign group members', ['Open Bulk Assignments.', 'Choose Group members.', 'Select the group and members from its branch.', 'Submit the assignment.'], 'groups.manage'),
                ],
                ['groups.view'],
            ),
            $this->article(
                'Operations',
                'Events',
                'Event stores a branch, joint, or church-wide activity with dates, times, location, flyer, and status.',
                ['Event' => 'A scheduled church activity separate from routine service attendance.'],
                ['branch_id', 'scope', 'name', 'description', 'flyer', 'start_date', 'end_date', 'start_time', 'end_time', 'location', 'status'],
                ['scope', 'name', 'start_date', 'status'],
                'events',
                [
                    $this->task('Create an event', ['Open Events and select Add.', 'Choose its scope and branch where applicable.', 'Enter the name, dates, location, description, and optional flyer.', 'Set the status and save.'], 'events.manage'),
                    $this->task('Update or cancel an event', ['Find and edit the event.', 'Update its schedule or status.', 'Save the changes.'], 'events.manage'),
                ],
                ['events.view'],
            ),
            $this->article(
                'Communications',
                'Broadcasts and audiences',
                'Broadcast stores a message, channel, schedule, and delivery status. BroadcastAudience defines the selected branch, department, group, or filtered member audience.',
                [
                    'Broadcast' => 'A church communication prepared for SMS, email, push, or WhatsApp delivery.',
                    'BroadcastAudience' => 'The audience rule attached to a broadcast, including optional branch and member filters.',
                ],
                ['title', 'message', 'channel', 'scheduled_at', 'audience_type', 'branch_id', 'department_id', 'group_id', 'filter_field', 'filter_operator', 'filter_value'],
                ['title', 'message', 'channel', 'audience_type', 'branch_id' => 'Required for branch, department, or group audiences'],
                'broadcasts/compose',
                [
                    $this->task('Compose a broadcast', ['Open Broadcasts.', 'Enter the title, message, and delivery channel.', 'Choose All members, Branch, Department, or Group.', 'Select the related branch and group/department where required.', 'For All members, optionally add a field/operator/value filter.', 'Save or schedule the broadcast. SMS broadcasts are delivered to members with phone numbers through BMS.'], 'broadcasts.create'),
                    $this->task('Send a broadcast', ['Review the saved broadcast and audience.', 'Choose Send when the message is ready.', 'Confirm delivery status afterward.'], 'broadcasts.send'),
                ],
                ['broadcasts.view'],
            ),
            $this->article(
                'Finance',
                'Income and giving references',
                'Income records money received. GivingType classifies the gift, Fund assigns its purpose, PaymentMethod records how it was received, and FinancialAccount identifies where it was deposited.',
                [
                    'Income' => 'A receipted incoming financial transaction linked to a branch, giving type, fund, payment method, and account.',
                    'GivingType' => 'A giving category such as Tithe or Offering, with an optional default fund.',
                    'Fund' => 'A financial purpose or restriction used to classify income, expenses, and pledges.',
                    'PaymentMethod' => 'A payment channel such as Cash, Bank Transfer, Card, or Mobile Money.',
                    'FinancialAccount' => 'A branch-linked cash, bank, or mobile-money account with currency and opening balance.',
                ],
                ['branch_id', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'giver_member_id', 'giver_name', 'giver_phone', 'amount', 'currency', 'transaction_reference', 'date', 'notes', 'status'],
                ['branch_id', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'amount', 'currency', 'date', 'status'],
                'income',
                [
                    $this->task('Record income', ['Open Finance → Income and select Add income.', 'Choose the branch and giving type; its default fund is selected automatically.', 'Choose the payment method and financial account.', 'Enter the amount, currency, date, and giver details where required.', 'Status defaults to Completed. Save the income.'], 'income.create'),
                    $this->task('Correct completed income', ['Completed income cannot be edited or deleted.', 'Create a reversal or correction transaction and retain the original audit trail.'], 'income.create'),
                    $this->task('Review income', ['Open Finance → Income.', 'Search by receipt, giver, date, branch, or transaction reference.', 'Open a record to review its classifications and status.']),
                ],
                ['income.view'],
            ),
            $this->article(
                'Finance',
                'Offering collections',
                'OfferingCollection records a counted offering for a service and branch. OfferingItem stores each giving-type, fund, payment-method, account, and amount line before posting it to Income.',
                [
                    'OfferingCollection' => 'A service offering batch with counted and verified workflow states.',
                    'OfferingItem' => 'One categorized amount within an offering collection, optionally linked to a giver and posted income.',
                ],
                ['service_id', 'branch_id', 'date', 'status', 'notes', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'member_id', 'giver_name', 'amount', 'currency'],
                ['service_id', 'branch_id', 'date', 'status', 'giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'amount', 'currency'],
                'offerings',
                [
                    $this->task('Capture an offering', ['Open Finance → Offerings and choose Capture.', 'Select the service, branch, and date.', 'Add one or more categorized offering items.', 'Save as Draft or Counted.'], 'offerings.capture'),
                    $this->task('Verify and post an offering', ['Open a Counted offering.', 'Verify the totals and choose Verify.', 'Choose Post to create linked income transactions.'], 'offerings.verify'),
                ],
                ['offerings.capture', 'offerings.verify'],
            ),
            $this->article(
                'Finance',
                'Expenses and approvals',
                'Expense records money requested or paid out. ExpenseType classifies it. ExpenseApproval records an approver’s decision and comments.',
                [
                    'Expense' => 'An outgoing transaction with recipient, classification, account, receipt, and workflow status.',
                    'ExpenseType' => 'A reusable expense category such as Utilities, Missions, or Supplies.',
                    'ExpenseApproval' => 'An immutable approval or rejection decision made by an authorized finance user.',
                ],
                ['branch_id', 'expense_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'recipient_name', 'recipient_contact', 'amount', 'currency', 'description', 'transaction_reference', 'date', 'receipt', 'notes', 'status'],
                ['branch_id', 'expense_type_id', 'fund_id', 'payment_method_id', 'financial_account_id', 'recipient_name', 'amount', 'currency', 'description', 'date', 'status'],
                'expenses',
                [
                    $this->task('Create an expense', ['Open Finance → Expenses and select Add expense.', 'Choose its branch, type, fund, payment method, and account.', 'Enter recipient, amount, description, and date.', 'Attach a receipt if available and submit.'], 'expenses.create'),
                    $this->task('Approve or reject an expense', ['Open Approval Queue.', 'Review the expense and supporting receipt.', 'Choose Approve or Reject and add comments if needed.', 'A requester cannot approve their own expense.'], 'expenses.approve'),
                    $this->task('Mark an expense paid', ['Open an approved expense in the Approval Queue.', 'Enter the transaction reference if available.', 'Choose Pay. Paid expenses become immutable.'], 'expenses.disburse'),
                ],
                ['expenses.view'],
            ),
            $this->article(
                'Finance',
                'Pledges and payments',
                'Pledge records a member’s promised giving amount and due date. PledgePayment links completed income to fulfillment of that pledge.',
                [
                    'Pledge' => 'A member commitment linked to a giving type and fund.',
                    'PledgePayment' => 'A completed income transaction allocated to a pledge.',
                ],
                ['member_id', 'giving_type_id', 'fund_id', 'pledged_amount', 'due_date', 'status', 'notes'],
                ['member_id', 'giving_type_id', 'fund_id', 'pledged_amount', 'status'],
                'pledges',
                [
                    $this->task('Create a pledge', ['Open Finance → Pledges and select Add.', 'Choose the member, giving type, and fund.', 'Enter the amount, optional due date, status, and notes.', 'Save the pledge.'], 'offerings.capture'),
                    $this->task('Record pledge fulfillment', ['Record the member’s giving as completed income.', 'Link the eligible income to the pledge payment workflow.', 'Confirm the pledge balance and status.'], 'offerings.capture'),
                ],
                ['financial_reports.view'],
            ),
            $this->article(
                'Finance',
                'Account transfers and financial reports',
                'AccountTransfer moves money between two financial accounts with initiation, approval, and completion states. Financial reports summarize completed income, paid expenses, and balances.',
                ['AccountTransfer' => 'A controlled movement of money between two different financial accounts.'],
                ['from_account_id', 'to_account_id', 'amount', 'currency', 'date', 'transaction_reference', 'notes', 'status'],
                ['from_account_id', 'to_account_id', 'amount', 'currency', 'date'],
                'account-transfers',
                [
                    $this->task('Initiate a transfer', ['Open Finance → Account Transfers.', 'Choose different source and destination accounts.', 'Enter amount, matching currency, date, and reference.', 'Submit for approval.'], 'transfers.create'),
                    $this->task('Approve and complete a transfer', ['Review the pending transfer.', 'Approve it if authorized and the account details are correct.', 'Complete it after the movement is confirmed.'], 'transfers.approve'),
                    $this->task('Review financial reports', ['Open Financial Report.', 'Choose a branch when needed.', 'Review completed income, paid expenses, and account balances.', 'Use Ask Data for charts and comparisons.'], 'financial_reports.view'),
                ],
                ['financial_reports.view'],
            ),
            $this->article(
                'Operations',
                'Assets',
                'Asset records church property. AssetType, AssetCondition, and AssetStatusOption provide consistent dropdown values for classification and lifecycle state.',
                [
                    'Asset' => 'A church-owned item assigned to a branch with purchase, quantity, condition, and status information.',
                    'AssetType' => 'A reusable category such as Vehicle, Equipment, or Furniture.',
                    'AssetCondition' => 'A reusable physical-condition value such as New, Good, or Damaged.',
                    'AssetStatusOption' => 'A reusable lifecycle state such as In use, In storage, or Disposed.',
                ],
                ['branch_id', 'name', 'asset_type_id', 'purchase_date', 'purchase_price', 'currency', 'condition', 'serial_number', 'quantity', 'status', 'notes'],
                ['branch_id', 'name', 'asset_type_id', 'status'],
                'assets',
                [
                    $this->task('Add an asset', ['Open Assets and select Add asset.', 'Choose the branch and asset type.', 'Enter name, quantity, condition, and status.', 'Add purchase and serial details where available, then save.'], 'assets.manage'),
                    $this->task('Update asset condition or status', ['Find and edit the asset.', 'Choose the current configured condition and status.', 'Add explanatory notes and save.'], 'assets.manage'),
                ],
                ['assets.view'],
            ),
            $this->article(
                'Administration',
                'Users, roles, permissions, and impersonation',
                'User is a login account. Role groups permissions. UserRole assigns a role church-wide or to multiple branches. Permission is a system capability used by authorization checks.',
                [
                    'User' => 'An authenticated leader or staff account optionally linked to a member profile.',
                    'Role' => 'A named collection of application permissions.',
                    'UserRole' => 'A role assignment for a user, optionally scoped to one or more branches.',
                    'Permission' => 'A system-defined ability such as members.view or expenses.create.',
                ],
                ['name', 'email', 'phone', 'password', 'pin', 'member_id', 'role_id', 'branch_ids', 'is_active'],
                ['name', 'email', 'password' => 'Required for a new user', 'role_id' => 'Required for a new user', 'is_active'],
                'users',
                [
                    $this->task('Create a user', ['Open Access Control → Users and select Add user.', 'Enter name, email, and initial password.', 'Choose a role and optional branch scope.', 'Save. The user receives sign-in instructions by email.'], 'users.manage'),
                    $this->task('Assign or change a role', ['Open Role Assignments.', 'Choose the user and role.', 'Choose one or more branches, or leave branches empty for church-wide scope.', 'Use Clear all to remove accidental branch selections, then save.'], 'roles.manage'),
                    $this->task('Impersonate a leader', ['Open Users as an App Administrator.', 'Choose Switch view for an active leader.', 'Review the app exactly as that leader sees it.', 'Select Stop impersonating to return to your account.'], null, ['App Administrator']),
                ],
                ['users.view', 'roles.manage'],
            ),
            $this->article(
                'Administration',
                'Setup and dropdown reference data',
                'Setup contains church settings and reusable reference models that populate dropdowns throughout the app. Supported reference lists can be created in batches.',
                [
                    'Church settings' => 'Organization defaults including church identity, address, timezone, and currency.',
                    'Reference data' => 'Reusable dropdown values such as service types, giving types, funds, payment methods, expense types, asset types, conditions, and statuses.',
                ],
                ['name', 'description', 'is_active', 'currency', 'timezone', 'default_fund_id', 'restricted', 'requires_giver'],
                ['name'],
                'church-settings',
                [
                    $this->task('Update church settings', ['Open Setup → Church Settings.', 'Update the church identity, contact details, address, default currency, or timezone.', 'Save changes; currency defaults apply to new financial records.'], 'church.settings'),
                    $this->task('Add reference data', ['Open the required page under Setup.', 'Select Add, complete required fields, and save.', 'Only active options appear in operational dropdowns where applicable.']),
                    $this->task('Bulk add reference data', ['Open a supported Setup listing and choose Bulk add.', 'Add rows for each item.', 'Complete required fields and submit.', 'The entire batch is validated before it is saved.']),
                ],
                [],
                ['App Administrator', 'Church Administrator'],
            ),
            $this->article(
                'Administration',
                'Files, logs, audits, and backups',
                'AuditLog and activity logs record important changes. Error logs support diagnosis. File System provides controlled downloads, and Backups creates recoverable application archives.',
                ['AuditLog' => 'A structured record of who changed a business record, what action occurred, and the captured changes.'],
                ['user_id', 'action', 'auditable_type', 'auditable_id', 'changes', 'ip_address', 'created_at'],
                [],
                'activity-logs',
                [
                    $this->task('Review activity and audit logs', ['Open Files & Logs.', 'Choose Activity Log or System Audit Logs.', 'Search and inspect the relevant entry.']),
                    $this->task('Inspect an error log', ['Open Error Logs.', 'Choose the relevant recent log.', 'Review or download it for diagnosis.']),
                    $this->task('Create and download a backup', ['Open Backups.', 'Choose Create backup and wait for completion.', 'Download the required backup archive and store it securely.']),
                    $this->task('Download an application file', ['Open File System.', 'Browse an allowed disk and folder.', 'Choose a file and download it.']),
                ],
                [],
                ['App Administrator'],
            ),
        ];
    }

    /**
     * @param  array<string, string>  $models
     * @param  list<string>  $fields
     * @param  list<string>|array<string, string>  $required
     * @param  list<array<string, mixed>>  $tasks
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function article(string $category, string $title, string $definition, array $models, array $fields, array $required, ?string $path, array $tasks, array $permissions = [], array $roles = []): array
    {
        return compact('category', 'title', 'definition', 'models', 'fields', 'required', 'path', 'tasks', 'permissions', 'roles');
    }

    /**
     * @param  list<string>  $steps
     * @param  list<string>  $roles
     * @return array<string, mixed>
     */
    private function task(string $title, array $steps, ?string $permission = null, array $roles = []): array
    {
        return compact('title', 'steps', 'permission', 'roles');
    }

    /** @param array<string, mixed> $article */
    private function articleIsVisible(User $user, array $article): bool
    {
        $hasRole = $article['roles'] === [] || collect($article['roles'])->intersect($this->roleNames)->isNotEmpty();
        $hasPermission = $article['permissions'] === [] || collect($article['permissions'])->contains(
            fn (string $permission): bool => $this->hasPermission($user, $permission),
        );

        return $hasRole && $hasPermission;
    }

    /** @param array<string, mixed> $article */
    private function prepareArticle(User $user, array $article): array
    {
        $required = collect($article['required'])->mapWithKeys(
            fn (string $value, int|string $key): array => is_int($key) ? [$value => 'Required'] : [$key => $value],
        );
        $article['fields'] = collect($article['fields'])->map(fn (string $field): array => [
            'name' => $this->fieldLabel($field),
            'definition' => $this->fieldDefinition($field),
            'requirement' => $required->get($field, 'Optional'),
        ])->all();
        $article['tasks'] = collect($article['tasks'])->filter(function (array $task) use ($user): bool {
            $hasRole = $task['roles'] === [] || collect($task['roles'])->intersect($this->roleNames)->isNotEmpty();
            $hasPermission = $task['permission'] === null || $this->hasPermission($user, $task['permission']);

            return $hasRole && $hasPermission;
        })->values()->all();
        $article['link'] = $article['path'] !== null ? backpack_url($article['path']) : null;
        $article['id'] = Str::slug($article['category'].'-'.$article['title']);
        unset($article['path'], $article['permissions'], $article['roles'], $article['required']);

        return $article;
    }

    private function hasPermission(User $user, string $permission): bool
    {
        $key = $user->getKey().'|'.$permission;

        return $this->permissionCache[$key] ??= $this->branchAccess->allows($user, $permission)
            || $this->branchAccess->accessibleBranchIds($user, $permission)->isNotEmpty();
    }

    private function fieldLabel(string $field): string
    {
        return Str::headline(Str::replaceEnd('_id', '', Str::replaceEnd('_ids', '', $field)));
    }

    private function fieldDefinition(string $field): string
    {
        return [
            'name' => 'The display name used throughout the application.',
            'code' => 'A short, unique identifier used for branch recognition and reporting.',
            'description' => 'Additional details explaining the record or transaction.',
            'notes' => 'Optional internal context or follow-up information.',
            'is_active' => 'Controls whether the record is currently available for use.',
            'status' => 'The record’s current workflow or lifecycle state.',
            'scope' => 'Whether the record applies to one branch, selected branches, or the whole church.',
            'branch_id' => 'The branch that owns or is responsible for the record.',
            'zone_id' => 'The optional zone that groups and oversees one or more branches.',
            'branch_ids' => 'One or more branches that define the user or record scope.',
            'primary_branch_id' => 'The member’s current main branch.',
            'branch_leader_id' => 'The active branch-leader appointment responsible for overseeing the member. It defaults to the branch’s first active leader and must be different from the shepherd.',
            'shepherd_id' => 'The member in the same primary branch who provides pastoral oversight.',
            'member_id' => 'The member connected to this record.',
            'member_ids' => 'The members selected for a bulk assignment.',
            'user_id' => 'The application user connected to the record.',
            'role_id' => 'The access-control role assigned to the user.',
            'first_name' => 'The person’s given name.',
            'middle_name' => 'The person’s optional middle name.',
            'last_name' => 'The person’s family name.',
            'membership_number' => 'The church-issued unique member reference.',
            'membership_status' => 'The person’s current membership category, defaulting to Member.',
            'phone' => 'The primary contact telephone number.',
            'alternative_phone' => 'A secondary contact telephone number.',
            'email' => 'The email address used for contact or sign-in.',
            'password' => 'The secret used for email-based sign-in.',
            'pin' => 'A 4–8 digit secret used for phone-based sign-in where enabled.',
            'address' => 'The physical or postal address.',
            'location' => 'The named venue or locality.',
            'gps_coordinates' => 'Optional latitude and longitude for the location.',
            'date_of_birth' => 'The person’s birth date.',
            'date_joined' => 'The date the member joined the church.',
            'joined_date' => 'The date an assignment or membership began.',
            'left_date' => 'The date an assignment or membership ended.',
            'start_date' => 'The first date of an event, appointment, or activity.',
            'end_date' => 'The final date of an event, appointment, or activity.',
            'date_started' => 'The date the branch began operating.',
            'date' => 'The transaction or activity date.',
            'start_time' => 'The scheduled starting time.',
            'end_time' => 'The scheduled ending time.',
            'gender' => 'The recorded gender used for pastoral and attendance reporting.',
            'marital_status' => 'The recorded marital status.',
            'occupation' => 'The person’s profession or main work.',
            'highest_education' => 'The highest education level recorded for the member.',
            'leaders' => 'One or more leadership appointments attached to a branch.',
            'leadership_title_id' => 'The formal title held in a leadership appointment.',
            'family_name' => 'The name used to identify a household.',
            'household_id' => 'The household receiving the selected members.',
            'relationship_id' => 'The relationship each selected member has within the household.',
            'is_head' => 'Identifies the household’s primary responsible person.',
            'service_id' => 'The service connected to attendance, offerings, or income.',
            'service_type' => 'The configured category of service.',
            'attendance' => 'A present or absent selection for every listed member.',
            'total_male' => 'The aggregate number of male attendees.',
            'total_female' => 'The aggregate number of female attendees.',
            'total_children' => 'The aggregate number of child attendees.',
            'total_members' => 'The aggregate number of member attendees.',
            'total_visitors' => 'The aggregate number of visitor attendees.',
            'department_id' => 'The department enabled for a branch.',
            'branch_department_id' => 'A specific department operating at a branch.',
            'department_role_id' => 'The role held by a department member.',
            'group_id' => 'The cell or group connected to the record.',
            'type' => 'The configured category or account/group type.',
            'title' => 'The short heading shown to recipients.',
            'message' => 'The full communication sent to recipients.',
            'channel' => 'The delivery method: SMS, email, push, or WhatsApp.',
            'scheduled_at' => 'The optional future date and time for delivery.',
            'audience_type' => 'The kind of recipient group selected for a broadcast.',
            'filter_field' => 'The member field used to narrow an all-members audience.',
            'filter_operator' => 'The comparison applied to the selected member field.',
            'filter_value' => 'The value compared against the selected member field.',
            'question' => 'The plain-language analytics question to answer.',
            'from' => 'The optional first date included in the analysis.',
            'to' => 'The optional final date included in the analysis.',
            'giving_type_id' => 'The configured category of giving.',
            'default_fund_id' => 'The fund automatically selected with a giving type.',
            'fund_id' => 'The fund or purpose assigned to the transaction.',
            'payment_method_id' => 'How money was received or paid.',
            'financial_account_id' => 'The cash, bank, or mobile-money account used.',
            'giver_member_id' => 'The member who made the gift, when known.',
            'giver_name' => 'The giver’s name when no member record is selected.',
            'giver_phone' => 'The giver’s optional telephone number.',
            'amount' => 'The monetary value of the transaction.',
            'currency' => 'The three-letter currency code, defaulting to the church currency.',
            'transaction_reference' => 'An external bank, mobile-money, receipt, or payment reference.',
            'expense_type_id' => 'The configured category of expense.',
            'recipient_name' => 'The person or organization receiving payment.',
            'recipient_contact' => 'Optional contact details for the payment recipient.',
            'receipt' => 'An uploaded image or PDF supporting the expense.',
            'pledged_amount' => 'The total amount the member committed to give.',
            'due_date' => 'The optional target date for pledge completion.',
            'from_account_id' => 'The financial account money leaves.',
            'to_account_id' => 'The different financial account money enters.',
            'asset_type_id' => 'The configured category of asset.',
            'purchase_date' => 'The date the asset was acquired.',
            'purchase_price' => 'The amount paid to acquire the asset.',
            'condition' => 'The asset’s configured physical condition.',
            'serial_number' => 'The manufacturer or church tracking reference.',
            'quantity' => 'The number of identical units represented by the record.',
            'restricted' => 'Indicates that a fund can only be used for its stated purpose.',
            'requires_giver' => 'Requires a member or giver name when recording this giving type.',
            'timezone' => 'The timezone used for church dates, schedules, and timestamps.',
            'action' => 'The recorded operation performed on a business record.',
            'auditable_type' => 'The model type changed by an audited operation.',
            'auditable_id' => 'The specific record changed by an audited operation.',
            'changes' => 'The before-and-after values captured by the audit.',
            'ip_address' => 'The network address associated with the audited request.',
            'created_at' => 'The date and time the record was created.',
        ][$field] ?? (str_ends_with($field, '_id') ? 'The related '.Str::lower($this->fieldLabel($field)).' selected for this record.' : 'The value stored for '.Str::lower($this->fieldLabel($field)).'.');
    }
}
