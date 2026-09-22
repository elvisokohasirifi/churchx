<?php

namespace App;

enum PermissionCode: string
{
    case MembersView = 'members.view';
    case MembersCreate = 'members.create';
    case MembersUpdate = 'members.update';
    case MembersDelete = 'members.delete';
    case VisitorsView = 'visitors.view';
    case VisitorsCreate = 'visitors.create';
    case VisitorsUpdate = 'visitors.update';
    case BranchesView = 'branches.view';
    case BranchesManage = 'branches.manage';
    case AttendanceView = 'attendance.view';
    case AttendanceCapture = 'attendance.capture';
    case IncomeView = 'income.view';
    case IncomeCreate = 'income.create';
    case IncomeUpdate = 'income.update';
    case OfferingsCapture = 'offerings.capture';
    case OfferingsVerify = 'offerings.verify';
    case ExpensesView = 'expenses.view';
    case ExpensesCreate = 'expenses.create';
    case ExpensesApprove = 'expenses.approve';
    case ExpensesDisburse = 'expenses.disburse';
    case TransfersCreate = 'transfers.create';
    case TransfersApprove = 'transfers.approve';
    case FinancialReportsView = 'financial_reports.view';
    case DepartmentsView = 'departments.view';
    case DepartmentsManage = 'departments.manage';
    case GroupsView = 'groups.view';
    case GroupsManage = 'groups.manage';
    case EventsView = 'events.view';
    case EventsManage = 'events.manage';
    case BroadcastsView = 'broadcasts.view';
    case BroadcastsCreate = 'broadcasts.create';
    case BroadcastsSend = 'broadcasts.send';
    case AssetsView = 'assets.view';
    case AssetsManage = 'assets.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case ChurchSettings = 'church.settings';
}
