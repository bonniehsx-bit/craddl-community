<template>
	<div class="file-upload" flex="main:center cross:center" @click="handleClick">
		<slot />
		<input ref="input" type="file" :accept="accept" :multiple="multiple" style="display: none" @change="handleChange">
	</div>
</template>

<script>
	import axios from 'axios'
	import {
		getToken
	} from '@/utils/auth'
	import {
		getMallId
	} from '@/utils/mall'
	export default {
		name: 'FileUpload',
		props: {
			disabled: Boolean,
			multiple: Boolean,
			max: Number,
			accept: String,
			params: Object,
			fields: Object,
			is_site: {
				type: Number,
				default: 0
			},
			// 同时上传几个。批量(拖入几十个文件)时全部一起发会把服务器的并发撑满,超时失败,所以排队、同时只跑几个
			concurrency: {
				type: Number,
				default: 3
			}
		},
		data() {
			return {
				dialogVisible: false,
				loading: true,
				checkedAttachments: [],
				files: []
			}
		},

		methods: {
			handleClick() {
				if (this.disabled) {
					return
				}
				this.$refs.input.value = null
				this.$refs.input.click()
			},
			handleChange(e) {
				if (!e.target.files) return
				this.uploadFiles(e.target.files)
			},
			/**
			 * 上传一批文件。点选(input)走这里,拖入文件时由父组件通过 ref 调用,拖入的批量不受 max 限制(opts.ignoreMax)。
			 * 事件:start(整批)→ progress({done,total})每完成一个 → success(单个成功)/ fail(单个失败)→ complete(整批结束,成功失败都会触发)
			 */
			uploadFiles(rawFiles, opts = {}) {
				const list = Array.from(rawFiles)
				if (!list.length) return
				if (!opts.ignoreMax && this.max && list.length > this.max) {
					this.$message.error('最多一次只能上传' + this.max + '个文件。')
					return
				}
				// 这一批自己的文件列表和已提示过的错误:下一批开始后 this.files 会换掉,收尾判断只看这一批
				const batch = list.map((raw) => ({ complete: false, response: null, rawFile: raw }))
				batch.toasted = new Set()
				this.files = batch
				this.$emit('start', batch)
				const queue = batch.slice()
				const pump = () => {
					while (queue.length && batch.running < this.concurrency) {
						batch.running++
						this.upload(queue.shift(), batch).then(() => {
							batch.running--
							pump()
						})
					}
				}
				batch.running = 0
				pump()
			},
			// 总是 resolve:成功失败都先把这个文件标记完成,再统一收尾,这样整批一定会触发 complete
			upload(file, batch) {
				let formData = new FormData()
				if (this.params) {
					for (let key in this.params) {
						if (this.params[key] !== undefined && this.params[key] !== null) {
							formData.append(key, this.params[key])
						}
					}
				}
				formData.append('is_site', this.is_site)
				formData.append('file', file.rawFile, file.rawFile.name)

				let mallId = getMallId();
				mallId = mallId ? mallId : 0;

				return axios.post('/common/file/upload', formData, {
					headers: {
						'Content-Type': 'multipart/form-data',
						'x-mall-id': mallId,
						'x-admin-token': getToken()
					},
				}).then(res => {
					if (res.data.code == 0) {
						file.response = res.data
						this.finish(file, batch, true)
					} else {
						// 一批里很多个文件常常是同一个原因(类型不允许、超过大小),相同的提示只弹一次
						const msg = res.data.msg
						if (msg && !batch.toasted.has(msg)) {
							batch.toasted.add(msg)
							this.$message.error(msg)
						}
						file.error = msg || '上传失败'
						this.finish(file, batch, false)
					}
				}).catch(() => {
					file.error = '网络异常'
					this.finish(file, batch, false)
				})
			},
			finish(file, batch, ok) {
				file.complete = true
				this.$emit(ok ? 'success' : 'fail', file)
				const done = batch.filter(f => f.complete).length
				this.$emit('progress', { done, total: batch.length })
				if (done === batch.length) {
					this.$emit('complete', batch)
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
</style>
