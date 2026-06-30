import {
  ArrowDownToLine,
  ArrowLeftRight,
  ArrowUpFromLine,
  Banknote,
  CalendarDays,
  ChartColumn,
  CheckCircle2,
  Database,
  FileSearch,
  Globe,
  HandCoins,
  Landmark,
  LayoutDashboard,
  ReceiptText,
  Scale,
  Settings,
  Store,
  Users,
  Wallet,
} from "lucide-react";

export type NavBadge = "new" | "soon";

export interface NavSubItem {
  id: string;
  title: string;
  url: string;
  icon?: typeof LayoutDashboard;
  badge?: NavBadge;
  disabled?: boolean;
  newTab?: boolean;
  permission?: string;
}

interface NavItemBase {
  id: string;
  title: string;
  icon?: typeof LayoutDashboard;
  badge?: NavBadge;
  disabled?: boolean;
  newTab?: boolean;
  permission?: string;
}

export interface NavMainLinkItem extends NavItemBase {
  url: string;
  subItems?: never;
}

export interface NavMainParentItem extends NavItemBase {
  subItems: NavSubItem[];
}

export type NavMainItem = NavMainLinkItem | NavMainParentItem;

export interface NavGroup {
  id: number;
  label?: string;
  items: NavMainItem[];
}

export const sidebarItems: NavGroup[] = [
  {
    id: 1,
    items: [
      {
        id: "dashboard",
        title: "Dashboard",
        url: "/dashboard/default",
        icon: LayoutDashboard,
        permission: "report.view",
      },
    ],
  },
  {
    id: 2,
    items: [
      {
        id: "master-data",
        title: "Master Data",
        icon: Database,
        subItems: [
          {
            id: "donors",
            title: "Donatur",
            url: "/dashboard/donors",
            icon: Users,
            permission: "donor.view",
          },
          // {
          //   id: "vendors",
          //   title: "Vendor",
          //   url: "/dashboard/vendors",
          //   icon: Store,
          //   permission: "donor.view",
          // },
          {
            id: "funds",
            title: "Dana Amanah",
            url: "/dashboard/funds",
            icon: HandCoins,
            permission: "fund.view",
          },
          {
            id: "accounts",
            title: "Kas / Bank",
            url: "/dashboard/accounts",
            icon: Landmark,
            permission: "account.view",
          },
          {
            id: "events",
            title: "Event",
            url: "/dashboard/programs",
            icon: CalendarDays,
            permission: "program.view",
          },
        ],
      },
    ],
  },
  {
    id: 3,
    items: [
      {
        id: "keuangan",
        title: "Keuangan",
        icon: Wallet,
        subItems: [
          {
            id: "receipts",
            title: "Penerimaan",
            url: "/dashboard/receipts",
            icon: ArrowDownToLine,
            permission: "receipt.view",
          },
          {
            id: "disbursements",
            title: "Pengeluaran",
            url: "/dashboard/disbursements",
            icon: ArrowUpFromLine,
            permission: "disbursement.view",
          },
          {
            id: "bank-fees",
            title: "Biaya Bank",
            url: "/dashboard/bank-fees",
            icon: Banknote,
            permission: "bankfee.view",
          },
          {
            id: "transfers",
            title: "Transfer",
            url: "/dashboard/transfers",
            icon: ArrowLeftRight,
            badge: "soon",
            disabled: true,
          },
        ],
      },
    ],
  },
  {
    id: 4,
    items: [
      {
        id: "approval",
        title: "Approval",
        url: "/dashboard/approvals",
        icon: CheckCircle2,
      },
      {
        id: "reports",
        title: "Laporan",
        icon: ChartColumn,
        permission: "report.view",
        subItems: [
          {
            id: "reports-index",
            title: "Semua Laporan",
            url: "/dashboard/reports",
            icon: ChartColumn,
            permission: "report.view",
          },
          {
            id: "report-fund-balances",
            title: "Saldo Dana Amanah",
            url: "/dashboard/reports/fund-balances",
            icon: HandCoins,
            permission: "report.view",
          },
          {
            id: "report-fund-mutation",
            title: "Mutasi Dana Amanah",
            url: "/dashboard/reports/fund-mutation",
            icon: HandCoins,
            permission: "report.view",
          },
          {
            id: "report-cash-book",
            title: "Buku Kas",
            url: "/dashboard/reports/cash-book",
            icon: Wallet,
            permission: "report.view",
          },
          {
            id: "report-bank-book",
            title: "Buku Bank",
            url: "/dashboard/reports/bank-book",
            icon: Landmark,
            permission: "report.view",
          },
          {
            id: "report-ledger",
            title: "Ledger",
            url: "/dashboard/reports/ledger",
            icon: ReceiptText,
            permission: "report.view",
          },
          {
            id: "report-by-program",
            title: "Per Event",
            url: "/dashboard/reports/by-program",
            icon: CalendarDays,
            permission: "report.view",
          },
          {
            id: "report-by-donor",
            title: "Per Donatur",
            url: "/dashboard/reports/by-donor",
            icon: Users,
            permission: "report.view",
          },
          {
            id: "report-by-vendor",
            title: "Per Vendor",
            url: "/dashboard/reports/by-vendor",
            icon: Store,
            permission: "report.view",
          },
          {
            id: "report-approval",
            title: "Approval",
            url: "/dashboard/reports/approval",
            icon: CheckCircle2,
            permission: "report.view",
          },
          {
            id: "report-audit",
            title: "Audit",
            url: "/dashboard/reports/audit",
            icon: FileSearch,
            permission: "audit.view",
          },
        ],
      },
      {
        id: "audits",
        title: "Audit Trail",
        url: "/dashboard/audits",
        icon: FileSearch,
        permission: "audit.view",
      },
      {
        id: "portal-donatur",
        title: "Portal Donatur",
        url: "/dashboard/portal-donatur",
        icon: Globe,
        permission: "portal.view",
      },
      {
        id: "opening-balances",
        title: "Saldo Awal",
        url: "/dashboard/opening-balances",
        icon: Scale,
        permission: "opening.view",
      },
      {
        id: "settings",
        title: "Pengaturan",
        url: "/dashboard/settings",
        icon: Settings,
        permission: "user.manage",
      },
    ],
  },
];
