import React from 'react'
import { Box, Typography, Card, CardContent, Grid, Alert } from '@mui/material'
import { People as PeopleIcon } from '@mui/icons-material'

const UsersPage: React.FC = () => {
  return (
    <Box>
      <Typography variant="h4" component="h1" gutterBottom>
        User Management
      </Typography>
      
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Alert severity="info" icon={<PeopleIcon />}>
            <Typography variant="h6">User Administration</Typography>
            <Typography>
              Manage system users, roles, and permissions. This section is only available to administrators and managers.
            </Typography>
          </Alert>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                User Accounts
              </Typography>
              <Typography color="text.secondary">
                Create, modify, and deactivate user accounts with role-based access control.
              </Typography>
            </CardContent>
          </Card>
        </Grid>
        
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                Role Management
              </Typography>
              <Typography color="text.secondary">
                Define and manage user roles (Admin, Manager, Operator, Viewer) and their permissions.
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  )
}

export default UsersPage