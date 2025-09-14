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
} from '@mui/material'
import { DataGrid, GridColDef, GridToolbar } from '@mui/x-data-grid'
import {
  Add as AddIcon,
  Search as SearchIcon,
  Edit as EditIcon,
  Delete as DeleteIcon,
  Warning as WarningIcon,
  CheckCircle as CheckCircleIcon,
} from '@mui/icons-material'
import { useQuery } from 'react-query'
import { stocksApi } from '../services/api'
import LoadingSpinner from '../components/LoadingSpinner'

const StocksPage: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('')
  const [page, setPage] = useState(0)
  const [pageSize, setPageSize] = useState(25)

  const { data: stocksData, isLoading } = useQuery(
    ['stocks', { search: searchTerm, skip: page * pageSize, limit: pageSize }],
    () => stocksApi.getStocks({ 
      search: searchTerm || undefined,
      skip: page * pageSize,
      limit: pageSize 
    }),
    { keepPreviousData: true }
  )

  const columns: GridColDef[] = [
    {
      field: 'sku',
      headerName: 'SKU',
      width: 150,
      renderCell: (params) => (
        <Typography variant="body2" fontWeight="medium">
          {params.value}
        </Typography>
      ),
    },
    {
      field: 'name',
      headerName: 'Product Name',
      width: 250,
      flex: 1,
    },
    {
      field: 'category',
      headerName: 'Category',
      width: 130,
      renderCell: (params) => (
        <Chip 
          label={params.value} 
          size="small" 
          variant="outlined"
          color="primary"
        />
      ),
    },
    {
      field: 'current_stock',
      headerName: 'Current Stock',
      width: 120,
      type: 'number',
      renderCell: (params) => (
        <Box display="flex" alignItems="center" gap={1}>
          <Typography variant="body2">
            {params.value?.toLocaleString()}
          </Typography>
          {params.row.is_low_stock && (
            <Tooltip title="Low stock alert">
              <WarningIcon color="warning" fontSize="small" />
            </Tooltip>
          )}
        </Box>
      ),
    },
    {
      field: 'available_stock',
      headerName: 'Available',
      width: 100,
      type: 'number',
      renderCell: (params) => (
        <Typography 
          variant="body2" 
          color={params.value <= 0 ? 'error' : 'text.primary'}
        >
          {params.value?.toLocaleString()}
        </Typography>
      ),
    },
    {
      field: 'unit_cost',
      headerName: 'Unit Cost',
      width: 100,
      type: 'number',
      renderCell: (params) => `$${params.value?.toFixed(2)}`,
    },
    {
      field: 'stock_value',
      headerName: 'Total Value',
      width: 120,
      type: 'number',
      renderCell: (params) => `$${params.value?.toLocaleString()}`,
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 100,
      renderCell: (params) => (
        <Chip
          label={params.value}
          size="small"
          color={params.value === 'active' ? 'success' : 'default'}
          icon={params.value === 'active' ? <CheckCircleIcon /> : undefined}
        />
      ),
    },
    {
      field: 'actions',
      headerName: 'Actions',
      width: 120,
      sortable: false,
      renderCell: (params) => (
        <Box>
          <Tooltip title="Edit">
            <IconButton size="small" color="primary">
              <EditIcon fontSize="small" />
            </IconButton>
          </Tooltip>
          <Tooltip title="Delete">
            <IconButton size="small" color="error">
              <DeleteIcon fontSize="small" />
            </IconButton>
          </Tooltip>
        </Box>
      ),
    },
  ]

  if (isLoading && !stocksData) {
    return <LoadingSpinner message="Loading stocks..." />
  }

  return (
    <Box>
      {/* Header */}
      <Box display="flex" justifyContent="space-between" alignItems="center" mb={3}>
        <Typography variant="h4" component="h1">
          Stock Management
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          size="large"
        >
          Add New Stock
        </Button>
      </Box>

      {/* Summary Cards */}
      <Grid container spacing={3} mb={3}>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Total Items
              </Typography>
              <Typography variant="h4" color="primary">
                {stocksData?.total?.toLocaleString() || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Low Stock Items
              </Typography>
              <Typography variant="h4" color="warning.main">
                {stocksData?.items?.filter((item: any) => item.is_low_stock).length || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Out of Stock
              </Typography>
              <Typography variant="h4" color="error.main">
                {stocksData?.items?.filter((item: any) => item.is_out_of_stock).length || 0}
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Typography color="text.secondary" gutterBottom>
                Total Value
              </Typography>
              <Typography variant="h4" color="success.main">
                ${stocksData?.items?.reduce((sum: number, item: any) => sum + (item.stock_value || 0), 0)?.toLocaleString() || 0}
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
                placeholder="Search stocks by SKU, name, or description..."
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
                <Chip label="All Items" variant="filled" color="primary" />
                <Chip label="Low Stock" variant="outlined" />
                <Chip label="Out of Stock" variant="outlined" />
                <Chip label="Active" variant="outlined" />
              </Box>
            </Grid>
          </Grid>
        </CardContent>
      </Card>

      {/* Data Grid */}
      <Card>
        <Box height={600}>
          <DataGrid
            rows={stocksData?.items || []}
            columns={columns}
            pageSize={pageSize}
            onPageSizeChange={setPageSize}
            rowsPerPageOptions={[10, 25, 50, 100]}
            page={page}
            onPageChange={setPage}
            rowCount={stocksData?.total || 0}
            paginationMode="server"
            loading={isLoading}
            components={{
              Toolbar: GridToolbar,
            }}
            componentsProps={{
              toolbar: {
                showQuickFilter: true,
                quickFilterProps: { debounceMs: 500 },
              },
            }}
            sx={{
              '& .MuiDataGrid-row:hover': {
                backgroundColor: 'action.hover',
              },
            }}
          />
        </Box>
      </Card>
    </Box>
  )
}

export default StocksPage