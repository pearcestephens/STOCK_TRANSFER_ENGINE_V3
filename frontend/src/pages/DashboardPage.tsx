import React, { useState, useEffect } from 'react'
import {
  Box,
  Grid,
  Paper,
  Typography,
  Card,
  CardContent,
  CardHeader,
  IconButton,
  Chip,
  List,
  ListItem,
  ListItemText,
  ListItemIcon,
  Divider,
  Button,
  Alert,
} from '@mui/material'
import {
  Inventory as InventoryIcon,
  TrendingUp as TrendingUpIcon,
  TrendingDown as TrendingDownIcon,
  Warning as WarningIcon,
  SwapHoriz as TransferIcon,
  Analytics as AnalyticsIcon,
  Refresh as RefreshIcon,
  GetApp as ExportIcon,
} from '@mui/icons-material'
import { useQuery, useQueryClient } from 'react-query'
import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
} from 'recharts'
import { format } from 'date-fns'

import { dashboardApi, analyticsApi } from '../services/api'
import LoadingSpinner from '../components/LoadingSpinner'
import toast from 'react-hot-toast'

// Colors for charts
const COLORS = ['#1976d2', '#dc004e', '#ed6c02', '#2e7d32', '#9c27b0']

interface MetricCardProps {
  title: string
  value: string | number
  subtitle?: string
  icon: React.ReactNode
  color?: 'primary' | 'secondary' | 'success' | 'warning' | 'error'
  trend?: 'up' | 'down' | 'neutral'
  trendValue?: string
}

const MetricCard: React.FC<MetricCardProps> = ({
  title,
  value,
  subtitle,
  icon,
  color = 'primary',
  trend,
  trendValue,
}) => {
  const getTrendIcon = () => {
    if (trend === 'up') return <TrendingUpIcon color="success" fontSize="small" />
    if (trend === 'down') return <TrendingDownIcon color="error" fontSize="small" />
    return null
  }

  return (
    <Card sx={{ height: '100%' }}>
      <CardContent>
        <Box display="flex" alignItems="center" justifyContent="space-between">
          <Box>
            <Typography color="text.secondary" gutterBottom variant="body2">
              {title}
            </Typography>
            <Typography variant="h4" component="div" color={`${color}.main`}>
              {value}
            </Typography>
            {subtitle && (
              <Typography color="text.secondary" variant="body2">
                {subtitle}
              </Typography>
            )}
            {trend && trendValue && (
              <Box display="flex" alignItems="center" mt={1}>
                {getTrendIcon()}
                <Typography variant="body2" sx={{ ml: 0.5 }}>
                  {trendValue}
                </Typography>
              </Box>
            )}
          </Box>
          <Box color={`${color}.main`}>{icon}</Box>
        </Box>
      </CardContent>
    </Card>
  )
}

const DashboardPage: React.FC = () => {
  const [selectedPeriod, setSelectedPeriod] = useState(30)
  const queryClient = useQueryClient()

  // Fetch dashboard data
  const { data: overview, isLoading: overviewLoading } = useQuery(
    'dashboard-overview',
    dashboardApi.getOverview,
    { refetchInterval: 30000 }
  )

  const { data: realTimeMetrics, isLoading: metricsLoading } = useQuery(
    'real-time-metrics',
    dashboardApi.getRealTimeMetrics,
    { refetchInterval: 10000 }
  )

  const { data: stockTrends, isLoading: trendsLoading } = useQuery(
    ['stock-trends', selectedPeriod],
    () => dashboardApi.getStockTrends({ days: selectedPeriod }),
    { refetchInterval: 60000 }
  )

  const { data: alerts, isLoading: alertsLoading } = useQuery(
    'dashboard-alerts',
    () => dashboardApi.getAlerts({ limit: 10 }),
    { refetchInterval: 30000 }
  )

  const { data: analyticsMetrics, isLoading: analyticsLoading } = useQuery(
    'analytics-metrics',
    analyticsApi.getDashboardMetrics,
    { refetchInterval: 300000 } // 5 minutes
  )

  const handleRefresh = () => {
    queryClient.invalidateQueries()
    toast.success('Dashboard refreshed!')
  }

  const handleExport = async () => {
    try {
      const data = await dashboardApi.exportData({
        format: 'json',
        data_type: 'overview',
        days: selectedPeriod,
      })
      
      // Create and download file
      const blob = new Blob([JSON.stringify(data, null, 2)], {
        type: 'application/json',
      })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `dashboard-export-${format(new Date(), 'yyyy-MM-dd')}.json`
      a.click()
      URL.revokeObjectURL(url)
      
      toast.success('Dashboard data exported!')
    } catch (error) {
      toast.error('Failed to export data')
    }
  }

  if (overviewLoading || metricsLoading) {
    return <LoadingSpinner message="Loading dashboard..." />
  }

  // Prepare chart data
  const stockMovementData = stockTrends?.trends?.map((trend: any) => ({
    date: format(new Date(trend.date), 'MMM dd'),
    inbound: trend.inbound,
    outbound: trend.outbound,
    net: trend.net_change,
  })) || []

  const inventoryData = [
    { name: 'Available', value: overview?.stock_summary?.total_active_stocks || 0 },
    { name: 'Low Stock', value: overview?.stock_summary?.low_stock_alerts || 0 },
    { name: 'Out of Stock', value: overview?.stock_summary?.out_of_stock || 0 },
  ]

  const transferStatusData = [
    { name: 'Pending', value: overview?.transfer_summary?.pending_transfers || 0 },
    { name: 'In Transit', value: overview?.transfer_summary?.in_transit_transfers || 0 },
    { name: 'Completed Today', value: overview?.transfer_summary?.completed_today || 0 },
  ]

  return (
    <Box>
      {/* Header */}
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4" component="h1">
          Dashboard
        </Typography>
        <Box display="flex" gap={1}>
          <Button
            variant="outlined"
            startIcon={<RefreshIcon />}
            onClick={handleRefresh}
          >
            Refresh
          </Button>
          <Button
            variant="outlined"
            startIcon={<ExportIcon />}
            onClick={handleExport}
          >
            Export
          </Button>
        </Box>
      </Box>

      {/* Key Metrics */}
      <Grid container spacing={3} mb={3}>
        <Grid item xs={12} sm={6} md={3}>
          <MetricCard
            title="Total Inventory Value"
            value={`$${(overview?.stock_summary?.total_inventory_value || 0).toLocaleString()}`}
            subtitle="Current market value"
            icon={<InventoryIcon fontSize="large" />}
            color="primary"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <MetricCard
            title="Active Stocks"
            value={overview?.stock_summary?.total_active_stocks || 0}
            subtitle="Items in inventory"
            icon={<InventoryIcon fontSize="large" />}
            color="success"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <MetricCard
            title="Pending Transfers"
            value={overview?.transfer_summary?.pending_transfers || 0}
            subtitle="Awaiting processing"
            icon={<TransferIcon fontSize="large" />}
            color="warning"
          />
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <MetricCard
            title="Low Stock Alerts"
            value={overview?.stock_summary?.low_stock_alerts || 0}
            subtitle="Items need reorder"
            icon={<WarningIcon fontSize="large" />}
            color="error"
          />
        </Grid>
      </Grid>

      {/* Charts Row */}
      <Grid container spacing={3} mb={3}>
        {/* Stock Movement Trends */}
        <Grid item xs={12} lg={8}>
          <Card>
            <CardHeader
              title="Stock Movement Trends"
              subheader={`Last ${selectedPeriod} days`}
              action={
                <Box display="flex" gap={1}>
                  {[7, 30, 90].map((days) => (
                    <Chip
                      key={days}
                      label={`${days}d`}
                      variant={selectedPeriod === days ? 'filled' : 'outlined'}
                      size="small"
                      onClick={() => setSelectedPeriod(days)}
                      color="primary"
                    />
                  ))}
                </Box>
              }
            />
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={stockMovementData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis />
                  <Tooltip />
                  <Legend />
                  <Line
                    type="monotone"
                    dataKey="inbound"
                    stroke="#2e7d32"
                    strokeWidth={2}
                    name="Inbound"
                  />
                  <Line
                    type="monotone"
                    dataKey="outbound"
                    stroke="#d32f2f"
                    strokeWidth={2}
                    name="Outbound"
                  />
                  <Line
                    type="monotone"
                    dataKey="net"
                    stroke="#1976d2"
                    strokeWidth={2}
                    name="Net Change"
                  />
                </LineChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* Inventory Distribution */}
        <Grid item xs={12} lg={4}>
          <Card>
            <CardHeader title="Inventory Status" />
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={inventoryData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {inventoryData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Bottom Row */}
      <Grid container spacing={3}>
        {/* Transfer Status */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardHeader title="Transfer Status" />
            <CardContent>
              <ResponsiveContainer width="100%" height={250}>
                <BarChart data={transferStatusData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" />
                  <YAxis />
                  <Tooltip />
                  <Bar dataKey="value" fill="#1976d2" />
                </BarChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>
        </Grid>

        {/* Recent Alerts */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardHeader 
              title="Recent Alerts" 
              subheader={`${alerts?.alerts?.length || 0} active alerts`}
            />
            <CardContent>
              {alertsLoading ? (
                <LoadingSpinner message="Loading alerts..." />
              ) : alerts?.alerts?.length ? (
                <List>
                  {alerts.alerts.slice(0, 5).map((alert: any, index: number) => (
                    <React.Fragment key={alert.id}>
                      <ListItem alignItems="flex-start">
                        <ListItemIcon>
                          <WarningIcon 
                            color={
                              alert.severity === 'critical' ? 'error' :
                              alert.severity === 'warning' ? 'warning' : 'info'
                            }
                          />
                        </ListItemIcon>
                        <ListItemText
                          primary={alert.title}
                          secondary={
                            <Box>
                              <Typography variant="body2" color="text.secondary">
                                {alert.message}
                              </Typography>
                              <Typography variant="caption" color="text.secondary">
                                {format(new Date(alert.created_at), 'MMM dd, HH:mm')}
                              </Typography>
                            </Box>
                          }
                        />
                        <Chip
                          label={alert.severity}
                          size="small"
                          color={
                            alert.severity === 'critical' ? 'error' :
                            alert.severity === 'warning' ? 'warning' : 'info'
                          }
                        />
                      </ListItem>
                      {index < 4 && <Divider />}
                    </React.Fragment>
                  ))}
                </List>
              ) : (
                <Alert severity="info">No active alerts</Alert>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  )
}

export default DashboardPage