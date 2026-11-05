<template>
    <div :class="[{'has-logo': showLogo && !settings.splitMenu, 'admin-dark': settings.sideTheme === 'theme-dark'}, `size-${size}`]" :style="{ backgroundColor: settings.sideTheme === 'theme-dark' ? variables.menuBackground : variables.menuLightBackground }">
        <logo v-if="showLogo && !settings.splitMenu" :collapse="isCollapse" />
        <el-scrollbar :class="settings.sideTheme" wrap-class="scrollbar-wrapper">
            <el-menu
                :default-active="activeMenu"
                :collapse="settings.splitMenu ? false : isCollapse"
                :background-color="settings.sideTheme === 'theme-dark' ? variables.menuBackground : variables.menuLightBackground"
                :text-color="settings.sideTheme === 'theme-dark' ? variables.menuColor : variables.menuLightColor"
                :unique-opened="true"
                :active-text-color="activeColor"
                :collapse-transition="false"
                mode="vertical"
            >
                <sidebar-item
                    v-for="(route, index) in sidebarRouters"
                    :key="route.path  + index"
                    :item="route"
                    :base-path="route.path"
                />
            </el-menu>
        </el-scrollbar>
        <!-- 账号区固定贴底。splitMenu(分栏)模式下左侧只是 54px 图标条、账号信息放不下,
             那种布局单独适配,这里先不渲染,顶栏的头像入口仍然可用。 -->
        <sidebar-account v-if="!settings.splitMenu" :collapse="isCollapse" />
    </div>
</template>

<script>
import { mapGetters, mapState } from "vuex";
import Logo from "./Logo";
import SidebarItem from "./SidebarItem";
import SidebarAccount from "./Account";
import variables from "@/styles/variables.scss";
import { menuActiveColor } from '@/utils/admin-theme';

export default {
    components: { SidebarItem, Logo, SidebarAccount },
    computed: {
        activeColor() { return menuActiveColor(this.settings, this.settings.sideTheme === 'theme-dark'); },
        ...mapState(["settings"]),
        ...mapGetters(["sidebarRouters", "sidebar"]),
        activeMenu() {
            const route = this.$route;
            const { meta, path } = route;
            // if set path, the sidebar will highlight the path you set
            if (meta.activeMenu) {
                return meta.activeMenu;
            }
            return path;
        },
        showLogo() {
            return this.$store.state.settings.sidebarLogo;
        },
        variables() {
            return variables;
        },
        size() {
            return this.$store.getters.size || 'medium';
        },
        isCollapse() {
            return !this.sidebar.opened;
        }
    }
};
</script>
