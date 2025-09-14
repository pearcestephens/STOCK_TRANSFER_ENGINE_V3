import React from 'react'
import { Box, Typography, Card, CardContent, Grid, Alert, Button } from '@mui/material'
import { Assessment as ReportsIcon, GetApp as ExportIcon } from '@mui/icons-material'

const ReportsPage: React.FC = () => {
  return (
    <Box>
      <Typography variant="h4" component="h1" gutterBottom>
        Reports & Analytics
      </Typography>
      
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Alert severity="info" icon={<ReportsIcon />}>
            <Typography variant="h6">Advanced Reporting System</Typography>
            <Typography>
              Generate comprehensive reports for inventory analysis, transfer history, and performance metrics. 
              Export data in various formats for integration with external systems.
            </Typography>
          </Alert>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Inventory Reports
              </Typography>
              <Typography color="text.secondary" paragraph>
                Detailed inventory status, stock levels, and valuation reports.
              </Typography>
              <Button variant="outlined" startIcon={<ExportIcon />}>
                Generate Report
              </Button>
            </CardContent>
          </Card>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Transfer Analysis
              </Typography>
              <Typography color="text.secondary" paragraph>
                Transfer performance, completion times, and efficiency metrics.
              </Typography>
              <Button variant="outlined" startIcon={<ExportIcon />}>
                Generate Report
              </Button>
            </CardContent>
          </Card>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Stock Movement History
              </Typography>
              <Typography color="text.secondary" paragraph>
                Comprehensive audit trail of all stock movements and transactions.
              </Typography>
              <Button variant="outlined" startIcon={<ExportIcon />}>
                Generate Report
              </Button>
            </CardContent>
          </Card>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Financial Analysis
              </Typography>
              <Typography color="text.secondary" paragraph>
                Cost analysis, inventory valuation, and financial performance metrics.
              </Typography>
              <Button variant="outlined" startIcon={<ExportIcon />}>
                Generate Report
              </Button>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  )
}

export default ReportsPage