import { Link } from '@inertiajs/react';
import {
    ClipboardList,
    BarChart3,
    Building2,
    History,
    Factory,
    LayoutGrid,
    Layers,
    Scissors,
    SlidersHorizontal,
    Truck,
    UserCog,
    Users,
    Target,
} from 'lucide-react';
import CuttingDieController from '@/actions/App/Http/Controllers/Catalog/CuttingDieController';
import CustomerController from '@/actions/App/Http/Controllers/Catalog/CustomerController';
import PaperSupplierController from '@/actions/App/Http/Controllers/Catalog/PaperSupplierController';
import PaperTypeController from '@/actions/App/Http/Controllers/Catalog/PaperTypeController';
import PressController from '@/actions/App/Http/Controllers/Catalog/PressController';
import ActivityLogController from '@/actions/App/Http/Controllers/Admin/ActivityLogController';
import CompanySettingsController from '@/actions/App/Http/Controllers/Admin/CompanySettingsController';
import PricingSettingsController from '@/actions/App/Http/Controllers/Admin/PricingSettingsController';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import JobController from '@/actions/App/Http/Controllers/Jobs/JobController';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import ProductionBoardController from '@/actions/App/Http/Controllers/ProductionBoardController';
import ReportsController from '@/actions/App/Http/Controllers/ReportsController';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const can = useCan();

    const workItems: NavItem[] = [
        { title: 'الرئيسية', href: dashboard(), icon: LayoutGrid },
        { title: 'الشغلانات', href: JobController.index(), icon: ClipboardList },
        {
            title: 'لوحة الإنتاج',
            href: ProductionBoardController(),
            icon: Factory,
        },
        ...(can('manage-leads')
            ? [{ title: 'الفرص', href: LeadController.index(), icon: Target }]
            : []),
        { title: 'العملاء', href: CustomerController.index(), icon: Users },
    ];

    const catalogItems: NavItem[] = [
        {
            title: 'أنواع الورق والأسعار',
            href: PaperTypeController.index(),
            icon: Layers,
        },
        {
            title: 'موردين الورق',
            href: PaperSupplierController.index(),
            icon: Truck,
        },
        {
            title: 'الاسطمبات',
            href: CuttingDieController.index(),
            icon: Scissors,
        },
        { title: 'المطابع', href: PressController.index(), icon: Factory },
    ];

    const adminItems: NavItem[] = [
        ...(can('manage-settings')
            ? [
                  {
                      title: 'التقارير',
                      href: ReportsController(),
                      icon: BarChart3,
                  },
                  {
                      title: 'سجل النشاط',
                      href: ActivityLogController(),
                      icon: History,
                  },
                  {
                      title: 'بيانات المصنع',
                      href: CompanySettingsController.edit(),
                      icon: Building2,
                  },
                  {
                      title: 'ثوابت التسعير',
                      href: PricingSettingsController.edit(),
                      icon: SlidersHorizontal,
                  },
              ]
            : []),
        ...(can('manage-users')
            ? [
                  {
                      title: 'المستخدمين',
                      href: UserController.index(),
                      icon: UserCog,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar side="right" collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="الشغل" items={workItems} />
                <NavMain label="الكتالوج" items={catalogItems} />
                {adminItems.length > 0 && (
                    <NavMain label="الإدارة" items={adminItems} />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
