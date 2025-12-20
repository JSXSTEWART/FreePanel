import { clsx } from "clsx";
import { ArrowUpIcon, ArrowDownIcon } from "@heroicons/react/24/solid";

interface StatCardProps {
  title: string;
  value: string | number;
  subtitle?: string;
  icon: React.ComponentType<{ className?: string }>;
  trend?: {
    value: number;
    isPositive: boolean;
    label?: string;
  };
  color?: "blue" | "green" | "yellow" | "red" | "purple" | "orange";
  loading?: boolean;
  onClick?: () => void;
}

const colorConfig = {
  blue: {
    bg: "bg-blue-500",
    bgLight: "bg-blue-50",
    text: "text-blue-600",
    ring: "ring-blue-500/20",
  },
  green: {
    bg: "bg-green-500",
    bgLight: "bg-green-50",
    text: "text-green-600",
    ring: "ring-green-500/20",
  },
  yellow: {
    bg: "bg-yellow-500",
    bgLight: "bg-yellow-50",
    text: "text-yellow-600",
    ring: "ring-yellow-500/20",
  },
  red: {
    bg: "bg-red-500",
    bgLight: "bg-red-50",
    text: "text-red-600",
    ring: "ring-red-500/20",
  },
  purple: {
    bg: "bg-purple-500",
    bgLight: "bg-purple-50",
    text: "text-purple-600",
    ring: "ring-purple-500/20",
  },
  orange: {
    bg: "bg-orange-500",
    bgLight: "bg-orange-50",
    text: "text-orange-600",
    ring: "ring-orange-500/20",
  },
};

export default function StatCard({
  title,
  value,
  subtitle,
  icon: Icon,
  trend,
  color = "blue",
  loading = false,
  onClick,
}: StatCardProps) {
  const config = colorConfig[color];

  if (loading) {
    return (
      <div className="card p-6">
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <div className="h-4 bg-gray-200 rounded animate-pulse w-24 mb-3" />
            <div className="h-8 bg-gray-200 rounded animate-pulse w-32 mb-2" />
            <div className="h-3 bg-gray-200 rounded animate-pulse w-20" />
          </div>
          <div className="w-12 h-12 bg-gray-200 rounded-xl animate-pulse" />
        </div>
      </div>
    );
  }

  return (
    <div
      className={clsx(
        "card p-6 transition-all duration-200",
        onClick && "cursor-pointer hover:shadow-md hover:border-gray-300",
      )}
      onClick={onClick}
      role={onClick ? "button" : undefined}
      tabIndex={onClick ? 0 : undefined}
      onKeyDown={onClick ? (e) => e.key === "Enter" && onClick() : undefined}
    >
      <div className="flex items-start justify-between">
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-gray-500 truncate">{title}</p>
          <p className="mt-2 text-3xl font-bold text-gray-900 tabular-nums">
            {value}
          </p>
          {subtitle && (
            <p className="mt-1 text-sm text-gray-500 truncate">{subtitle}</p>
          )}
          {trend && (
            <div className="mt-3 flex items-center gap-1">
              <span
                className={clsx(
                  "inline-flex items-center gap-0.5 text-sm font-medium",
                  trend.isPositive ? "text-green-600" : "text-red-600",
                )}
              >
                {trend.isPositive ? (
                  <ArrowUpIcon className="w-4 h-4" />
                ) : (
                  <ArrowDownIcon className="w-4 h-4" />
                )}
                {Math.abs(trend.value)}%
              </span>
              <span className="text-sm text-gray-500">
                {trend.label || "from last month"}
              </span>
            </div>
          )}
        </div>
        <div
          className={clsx(
            "flex-shrink-0 p-3 rounded-xl ring-4",
            config.bg,
            config.ring,
          )}
        >
          <Icon className="w-6 h-6 text-white" aria-hidden="true" />
        </div>
      </div>
    </div>
  );
}

// Compact variant for denser layouts
interface CompactStatCardProps {
  label: string;
  value: string | number;
  icon?: React.ComponentType<{ className?: string }>;
  color?: keyof typeof colorConfig;
}

export function CompactStatCard({
  label,
  value,
  icon: Icon,
  color = "blue",
}: CompactStatCardProps) {
  const config = colorConfig[color];

  return (
    <div className="card p-4">
      <div className="flex items-center gap-3">
        {Icon && (
          <div className={clsx("p-2 rounded-lg", config.bgLight)}>
            <Icon className={clsx("w-5 h-5", config.text)} />
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium text-gray-500 truncate">{label}</p>
          <p className="text-lg font-bold text-gray-900 tabular-nums">
            {value}
          </p>
        </div>
      </div>
    </div>
  );
}
