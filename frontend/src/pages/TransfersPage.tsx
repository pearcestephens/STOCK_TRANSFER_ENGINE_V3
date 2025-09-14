import React, { useState } from 'react'
import {
  Box,
  Typography,
  Button,
  Card,
  CardContent,
  Grid,
  TextField,
  InputAdornment,
  Chip,
  IconButton,
  Tooltip,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
} from '@mui/material'
import { DataGrid, GridColDef } from '@mui/x-data-grid'
import {
  Add as AddIcon,
  Search as SearchIcon,
  Visibility as ViewIcon,
  Check as ApproveIcon,
  Close as CancelIcon,
  LocalShipping as ShippingIcon,
} from '@mui/icons-material'
import { useQuery } from 'react-query'
import { format } from 'date-fns'
import { transfersApi } from '../services/api'
import LoadingSpinner from '../components/LoadingSpinner'

const TransfersPage: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('')
  const [selectedStatus, setSelectedStatus] = useState<string>('')
  const [page, setPage] = useState(0)
  const [pageSize, setPageSize] = useState(25)
  const [selectedTransfer, setSelectedTransfer] = useState<any>(null)
  const [detailsOpen, setDetailsOpen] = useState(false)

  const { data: transfersData, isLoading } = useQuery(
    ['transfers', { search: searchTerm, status: selectedStatus, skip: page * pageSize, limit: pageSize }],
    () => transfersApi.getTransfers({ 
      status: selectedStatus || undefined,
      skip: page * pageSize,
      limit: pageSize 
    }),
    { keepPreviousData: true }
  )

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return 'success'
      case 'in_transit': return 'info'
      case 'pending': return 'warning'
      case 'cancelled': return 'error'
      case 'failed': return 'error'
      default: return 'default'
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed': return <Check />
      case 'in_transit': return <ShippingIcon />
      case 'pending': return <Clock />
      case 'cancelled': return <CancelIcon />
      default: return null
    }
  }

  const columns: GridColDef[] = [
    {
      field: 'transfer_number',
      headerName: 'Transfer #',
      width: 150,
      renderCell: (params) => (
        <Typography variant="body2" fontWeight="medium">
          {params.value}
        </Typography>
      ),
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 130,
      renderCell: (params) => (
        <Chip
          label={params.value.replace('_', ' ')}
          size="small"
          color={getStatusColor(params.value) as any}
          variant="filled"
        />
      ),
    },
    {
      field: 'from_location',
      headerName: 'From',
      width: 150,
    },
    {
      field: 'to_location',
      headerName: 'To',
      width: 150,
    },
    {
      field: 'total_items',
      headerName: 'Items',
      width: 80,
      type: 'number',
    },
    {
      field: 'total_quantity',
      headerName: 'Quantity',
      width: 100,
      type: 'number',
      renderCell: (params) => params.value?.toLocaleString(),
    },
    {
      field: 'priority',
      headerName: 'Priority',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          size="small"
          color={
            params.value === 'urgent' ? 'error' :
            params.value === 'high' ? 'warning' : 'default'
          }
          variant="outlined"
        />
      ),
    },
    {
      field: 'created_at',
      headerName: 'Created',
      width: 130,
      renderCell: (params) => format(new Date(params.value), 'MMM dd, yyyy'),
    },
    {
      field: 'completed_date',
      headerName: 'Completed',
      width: 130,
      renderCell: (params) => 
        params.value ? format(new Date(params.value), 'MMM dd, yyyy') : '-',
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 150,
      sortable: false,
      renderCell: (params) => (
        <Box>
          <Tooltip title="View Details">
            <IconButton 
              size="small" 
              color="primary"
              onClick={() => {
                setSelectedTransfer(params.row)
                setDetailsOpen(true)
              }}
            >
              <ViewIcon fontSize="small" />
            </IconButton>
          </Tooltip>
          {params.row.status === 'pending' && (
            <Tooltip title="Approve">
              <IconButton size="small" color="success">
                <ApproveIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
          {(params.row.status === 'pending' || params.row.status === 'draft') && (
            <Tooltip title="Cancel">
              <IconButton size="small" color="error">
                <CancelIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
        </Box>
      ),
    },
  ]

  const statusOptions = [
    { value: '', label: 'All Transfers' },
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_transit', label: 'In Transit' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
  ]

  if (isLoading && !transfersData) {
    return <LoadingSpinner message="Loading transfers..." />
  }

  return (
    <Box>
      {/* Header */}
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4" component="h1">
          Stock Transfers
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          size="large"
        >
          Create Transfer
        </Button>
      </Box>

      {/* Summary Cards */}
      <Grid container spacing={3} mb={3}>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Total Transfers
              </Typography>
              <Typography variant="h4" color="primary">
                {transfersData?.total?.toLocaleString() || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Pending Approval
              </Typography>
              <Typography variant="h4" color="warning.main">
                {transfersData?.items?.filter((item: any) => item.status === 'pending').length || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                In Transit
              </Typography>
              <Typography variant="h4" color="info.main">
                {transfersData?.items?.filter((item: any) => item.status === 'in_transit').length || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Completed Today
              </Typography>
              <Typography variant="h4" color="success.main">
                {transfersData?.items?.filter((item: any) => {
                  if (!item.completed_date) return false
                  const completedDate = new Date(item.completed_date)
                  const today = new Date()
                  return completedDate.toDateString() === today.toDateString()
                }).length || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Search and Filters */}
      <Card sx={{ mb: 3 }}>
        <CardContent>
          <Grid container spacing={2} alignItems="center">
            <Grid item xs={12} md={6}>
              <TextField
                fullWidth
                placeholder="Search transfers by number, location, or items..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                InputProps={{
                  startAdornment: (
                    <InputAdornment position="start">
                      <SearchIcon />
                    </InputAdornment>
                  ),
                }}
              />
            </Grid>
            <Grid item xs={12} md={6}>
              <Box display="flex" gap={1} flexWrap="wrap">
                {statusOptions.map((option) => (
                  <Chip
                    key={option.value}
                    label={option.label}
                    variant={selectedStatus === option.value ? 'filled' : 'outlined'}
                    color={selectedStatus === option.value ? 'primary' : 'default'}
                    onClick={() => setSelectedStatus(option.value)}
                    clickable
                  />
                ))}
              </Box>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      {/* Data Grid */}
      <Card>
        <Box height={600}>
          <DataGrid
            rows={transfersData?.items || []}
            columns={columns}
            pageSize={pageSize}
            onPageSizeChange={setPageSize}
            rowsPerPageOptions={[10, 25, 50, 100]}
            page={page}
            onPageChange={setPage}
            rowCount={transfersData?.total || 0}
            paginationMode="server"
            loading={isLoading}
            sx={{
              '& .MuiDataGrid-row:hover': {
                backgroundColor: 'action.hover',
              },
            }}
          />
        </Box>
      </Card>

      {/* Transfer Details Dialog */}
      <Dialog
        open={detailsOpen}
        onClose={() => setDetailsOpen(false)}
        maxWidth="md"
        fullWidth
      >
        <DialogTitle>
          Transfer Details - {selectedTransfer?.transfer_number}
        </DialogTitle>
        <DialogContent>
          {selectedTransfer && (
            <Grid container spacing={2}>
              <Grid item xs={12} sm={6}>
                <Typography variant="subtitle2">Status</Typography>
                <Chip
                  label={selectedTransfer.status.replace('_', ' ')}
                  color={getStatusColor(selectedTransfer.status) as any}
                  size="small"
                />
              </Grid>
              <Grid item xs={12} sm={6}>
                <Typography variant="subtitle2">Priority</Typography>
                <Typography>{selectedTransfer.priority}</Typography>
              </Grid>
              <Grid item xs={12} sm={6}>
                <Typography variant="subtitle2">From Location</Typography>
                <Typography>{selectedTransfer.from_location}</Typography>
              </Grid>
              <Grid item xs={12} sm={6}>
                <Typography variant="subtitle2">To Location</Typography>
                <Typography>{selectedTransfer.to_location}</Typography>
              </Grid>
              <Grid item xs={12}>
                <Typography variant="subtitle2">Reason</Typography>
                <Typography>{selectedTransfer.reason || 'No reason provided'}</Typography>
              </Grid>
              <Grid item xs={12}>
                <Typography variant="subtitle2">Notes</Typography>
                <Typography>{selectedTransfer.notes || 'No notes'}</Typography>
              </Grid>
            </Grid>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDetailsOpen(false)}>Close</Button>
        </DialogActions>
      </Dialog>
    </Box>
  )
}

export default TransfersPage