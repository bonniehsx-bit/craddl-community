import axios from 'axios'
import { getToken } from '@/utils/auth'
import { getMallId } from '@/utils/mall'

/**
 * 通用的文件上传函数
 * @param {File} rawFile - 用户选择的原始文件对象
 * @param {string} uploadUrl - 上传的目标URL
 * @param {object} params - 额外的表单参数
 * @returns {Promise<object>} - 返回包含文件信息的Promise
 */
export function uploadFile(rawFile, uploadUrl = '/common/file/upload', params = {}) {
  return new Promise(async (resolve, reject) => {
    try {
      const formData = new FormData()

      // 添加额外参数
      Object.entries(params).forEach(([key, value]) => {
        formData.append(key, value)
      })

      // 添加文件
      formData.append('file', rawFile, rawFile.name)

      // 获取商城ID
      const mallId = getMallId() || 0

      // 上传请求
      const response = await axios.post(uploadUrl, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
          'x-mall-id': mallId,
          'x-admin-token': getToken()
        },
        // 如果需要进度条，可以在这里添加 onUploadProgress
      })

      if (response.data.code === 0) {
        // 上传成功，返回后端给的文件数据
        resolve(response.data.data)
      } else {
        // 上传失败，抛出后端返回的错误信息
        throw new Error(response.data.msg || '上传失败')
      }
    } catch (error) {
      // 网络或其他错误
      reject(error)
    }
  })
}
