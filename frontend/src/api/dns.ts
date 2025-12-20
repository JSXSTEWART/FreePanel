import client from './client'

export interface DnsZone {
  domain: string
  ttl: number
  serial: number
  refresh: number
  retry: number
  expire: number
  minimum: number
  records_count: number
}

export interface DnsRecord {
  id: number
  type: 'A' | 'AAAA' | 'CNAME' | 'MX' | 'TXT' | 'NS' | 'SRV' | 'CAA' | 'PTR'
  name: string
  content: string
  ttl: number
  priority?: number
  created_at: string
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const dnsApi = {
  getZone: async (domainId: number): Promise<DnsZone> => {
    const response = await client.get<ApiResponse<DnsZone>>(`/dns/zones/${domainId}`)
    return response.data.data
  },

  getRecords: async (domainId: number): Promise<DnsRecord[]> => {
    const response = await client.get<ApiResponse<DnsRecord[]>>(`/dns/zones/${domainId}/records`)
    return response.data.data
  },

  addRecord: async (domainId: number, data: {
    type: DnsRecord['type']
    name: string
    content: string
    ttl?: number
    priority?: number
  }): Promise<DnsRecord> => {
    const response = await client.post<ApiResponse<DnsRecord>>(`/dns/zones/${domainId}/records`, data)
    return response.data.data
  },

  updateRecord: async (domainId: number, recordId: number, data: {
    content?: string
    ttl?: number
    priority?: number
  }): Promise<DnsRecord> => {
    const response = await client.put<ApiResponse<DnsRecord>>(
      `/dns/zones/${domainId}/records/${recordId}`,
      data
    )
    return response.data.data
  },

  deleteRecord: async (domainId: number, recordId: number): Promise<void> => {
    await client.delete(`/dns/zones/${domainId}/records/${recordId}`)
  },

  resetZone: async (domainId: number): Promise<DnsZone> => {
    const response = await client.post<ApiResponse<DnsZone>>(`/dns/zones/${domainId}/reset`)
    return response.data.data
  },

  exportZone: async (domainId: number): Promise<string> => {
    const response = await client.get<ApiResponse<string>>(`/dns/zones/${domainId}/export`)
    return response.data.data
  },
}

export default dnsApi
