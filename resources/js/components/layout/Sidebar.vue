<script setup>
import { Link, usePage } from "@inertiajs/vue3";
import { ref } from "vue";
import {
    BarChart3,
    Building2,
    Boxes,
    ChevronDown,
    LayoutDashboard,
    LogOut,
    Package,
    Settings,
    Truck,
    User,
    Users,
} from "lucide-vue-next";

const page = usePage();
const sideNavLogo =
    "/assets/side-nav-logo/Gemini_Generated_Image_7wme0a7wme0a7wme-removebg-preview.png";

const items = [
    { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
    { label: "Products", href: "/products", icon: Package },
    { label: "Inventories", href: "/inventories", icon: Boxes },
    {
        label: "Sales Management",
        icon: BarChart3,
        children: [
            { label: "Sales Report", href: "/sales-report" },
            {
                label: "Product Profit Analysis",
                href: "/product-profit-analysis",
            },
        ],
    },
    { label: "Trucking", href: "/trucking", icon: Truck },
    { label: "Employees", href: "/employees", icon: User },
    { label: "Branches", href: "/branches", icon: Building2 },
    { label: "Users", href: "/users", icon: Users },
    { label: "Settings", href: "/settings", icon: Settings },
];

const isActive = (href) => page.url === href || page.url.startsWith(`${href}/`);
const isGroupActive = (item) =>
    Array.isArray(item.children) &&
    item.children.some((child) => isActive(child.href));
const getGroupKey = (item) => item.label;

const openGroupKeys = ref(
    new Set(
        items
            .filter((item) => isGroupActive(item))
            .map((item) => getGroupKey(item)),
    ),
);

const isGroupOpen = (item) => openGroupKeys.value.has(getGroupKey(item));

const toggleGroup = (item) => {
    const key = getGroupKey(item);
    const next = new Set(openGroupKeys.value);

    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }

    openGroupKeys.value = next;
};
</script>

<template>
    <aside class="sidebar">
        <div class="sidebar__brand">
            <img
                :src="sideNavLogo"
                alt="Naga Alta Agri Corp"
                class="sidebar__logo"
            />
        </div>
        <nav class="sidebar__nav">
            <template v-for="item in items" :key="item.label">
                <div
                    v-if="item.children"
                    class="sidebar__group"
                    :class="{
                        'sidebar__group--active': isGroupActive(item),
                        'sidebar__group--open': isGroupOpen(item),
                    }"
                >
                    <button
                        type="button"
                        class="sidebar__group-header"
                        :title="item.label"
                        :aria-expanded="isGroupOpen(item)"
                        @click="toggleGroup(item)"
                    >
                        <component :is="item.icon" class="sidebar__icon" />
                        <span class="sidebar__label">{{ item.label }}</span>
                        <ChevronDown class="sidebar__group-chevron" />
                    </button>

                    <div
                        class="sidebar__subnav"
                        :class="{ 'sidebar__subnav--open': isGroupOpen(item) }"
                    >
                        <Link
                            v-for="child in item.children"
                            :key="child.href"
                            :href="child.href"
                            :title="child.label"
                            class="sidebar__sublink"
                            :class="{
                                'sidebar__sublink--active': isActive(
                                    child.href,
                                ),
                            }"
                        >
                            <span class="sidebar__sublink-dot" />
                            <span class="sidebar__sublink-label">
                                {{ child.label }}
                            </span>
                        </Link>
                    </div>
                </div>

                <Link
                    v-else
                    :href="item.href"
                    :title="item.label"
                    class="sidebar__link"
                    :class="{ 'sidebar__link--active': isActive(item.href) }"
                >
                    <component :is="item.icon" class="sidebar__icon" />
                    <span class="sidebar__label">{{ item.label }}</span>
                </Link>
            </template>
        </nav>

        <div class="sidebar__footer">
            <Link
                href="/logout"
                method="post"
                as="button"
                title="Logout"
                class="sidebar__logout"
            >
                <LogOut class="sidebar__icon" />
                <span class="sidebar__label">Logout</span>
            </Link>
        </div>
    </aside>
</template>
