// 文件路径: src/store/modules/app.js

export default {
    // [核心] 必须开启命名空间，这样 'app/SET_SYSTEM_INFO' 才能被正确解析
    namespaced: true,

	state: {
        // [新增] 增加一个 state 属性来存储系统信息，并初始化为 null
        systemInfo: null, 

		versions: {
			mpwx:'',
			ios:'',
			android:'',
		}
	},

	mutations: {
        // [新增] 添加缺失的 SET_SYSTEM_INFO mutation
        // 它的作用就是接收外部传入的数据(payload)，并将其赋值给 state.systemInfo
        SET_SYSTEM_INFO(state, systemInfo) {
            state.systemInfo = systemInfo;
        },

		setVersions(state, versions) {
			state.versions = versions;
		}
	},

	actions: {
        // [可选但推荐] 为 SET_SYSTEM_INFO 添加一个对应的 action
        // 这样在组件中可以通过 dispatch 调用，更符合 Vuex 的最佳实践
        setSystemInfo(context, systemInfo) {
            context.commit('SET_SYSTEM_INFO', systemInfo);
        },

		setVersions(context, versions) {
			context.commit('setVersions', versions);
		},
	}
};