import { useState, useEffect, useCallback } from 'react'
import { planApi, Plan, AccountUsage, Feature } from '../api'

interface UsePlanResult {
  plan: Plan | null
  usage: AccountUsage | null
  features: Feature[]
  loading: boolean
  error: string | null
  hasFeature: (featureName: string) => boolean
  isOverQuota: (resource: 'disk' | 'bandwidth' | 'domains' | 'email' | 'databases' | 'ftp') => boolean
  getUsagePercent: (resource: 'disk' | 'bandwidth' | 'domains' | 'email' | 'databases' | 'ftp') => number
  refresh: () => Promise<void>
}

export function usePlan(): UsePlanResult {
  const [plan, setPlan] = useState<Plan | null>(null)
  const [usage, setUsage] = useState<AccountUsage | null>(null)
  const [features, setFeatures] = useState<Feature[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fetchPlanData = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)

      const [planData, usageData, featuresData] = await Promise.all([
        planApi.getCurrentPlan(),
        planApi.getUsage(),
        planApi.getFeatures(),
      ])

      setPlan(planData)
      setUsage(usageData)
      setFeatures(featuresData)
    } catch (err) {
      setError('Failed to load plan information')
      console.error('Error loading plan data:', err)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchPlanData()
  }, [fetchPlanData])

  const hasFeature = useCallback(
    (featureName: string): boolean => {
      const feature = features.find((f) => f.name === featureName)
      return feature?.is_enabled ?? false
    },
    [features]
  )

  const isOverQuota = useCallback(
    (resource: 'disk' | 'bandwidth' | 'domains' | 'email' | 'databases' | 'ftp'): boolean => {
      if (!usage) return false

      const quotaMap: Record<string, { used: string; limit: string }> = {
        disk: { used: 'disk_used', limit: 'disk_quota' },
        bandwidth: { used: 'bandwidth_used', limit: 'bandwidth_quota' },
        domains: { used: 'domains_used', limit: 'domains_limit' },
        email: { used: 'email_accounts_used', limit: 'email_accounts_limit' },
        databases: { used: 'databases_used', limit: 'databases_limit' },
        ftp: { used: 'ftp_accounts_used', limit: 'ftp_accounts_limit' },
      }

      const map = quotaMap[resource]
      if (!map) return false

      const usedValue = (usage as Record<string, number>)[map.used]
      const limitValue = (usage as Record<string, number>)[map.limit]

      // 0 means unlimited
      if (limitValue === 0) return false

      return usedValue >= limitValue
    },
    [usage]
  )

  const getUsagePercent = useCallback(
    (resource: 'disk' | 'bandwidth' | 'domains' | 'email' | 'databases' | 'ftp'): number => {
      if (!usage) return 0

      const percentMap: Record<string, string> = {
        disk: 'disk_percentage',
        bandwidth: 'bandwidth_percentage',
        domains: 'domains_used',
        email: 'email_accounts_used',
        databases: 'databases_used',
        ftp: 'ftp_accounts_used',
      }

      const limitMap: Record<string, string> = {
        domains: 'domains_limit',
        email: 'email_accounts_limit',
        databases: 'databases_limit',
        ftp: 'ftp_accounts_limit',
      }

      // For disk and bandwidth, we have direct percentage
      if (resource === 'disk' || resource === 'bandwidth') {
        return (usage as Record<string, number>)[percentMap[resource]] || 0
      }

      // For other resources, calculate percentage
      const used = (usage as Record<string, number>)[percentMap[resource]]
      const limit = (usage as Record<string, number>)[limitMap[resource]]

      if (limit === 0) return 0 // Unlimited
      return Math.round((used / limit) * 100)
    },
    [usage]
  )

  return {
    plan,
    usage,
    features,
    loading,
    error,
    hasFeature,
    isOverQuota,
    getUsagePercent,
    refresh: fetchPlanData,
  }
}

export default usePlan
