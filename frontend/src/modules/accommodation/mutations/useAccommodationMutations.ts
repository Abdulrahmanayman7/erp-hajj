import { useMutation, useQueryClient } from '@tanstack/vue-query'

import {
  arriveAssignment,
  cancelConfirmedAssignment,
  checkInAssignment,
  checkOutAssignment,
  confirmAssignment,
  confirmFamilyAssignments,
  createAssignment,
  createFacility,
  createPosition,
  createSeasonFacility,
  createSeasonPosition,
  createSeasonUnit,
  createStructure,
  createUnit,
  deleteAssignment,
  deleteFacility,
  deletePosition,
  deleteSeasonFacility,
  deleteSeasonPosition,
  deleteSeasonUnit,
  deleteStructure,
  deleteUnit,
  grantFacilityScope,
  grantSupervisorScope,
  relocateAssignment,
  revokeFacilityScope,
  revokeSupervisorScope,
  updateAssignment,
  updateFacility,
  updatePosition,
  updateSeasonFacility,
  updateSeasonPosition,
  updateSeasonUnit,
  updateStructure,
  updateUnit,
} from '../api/accommodationApi'
import type {
  CancelAssignmentPayload,
  OpsOccurredAtPayload,
  RelocateAssignmentPayload,
} from '../types/accommodation'
import { accommodationQueryKey } from '../queries/useAccommodationQueries'

function useInvalidateAccommodation() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: accommodationQueryKey })
}

export function useCreateFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: createFacility, onSuccess: invalidate })
}

export function useUpdateFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateFacility>[1] }) =>
      updateFacility(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteFacility, onSuccess: invalidate })
}

export function useCreateStructureMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({
      facilityId,
      payload,
    }: {
      facilityId: number
      payload: Parameters<typeof createStructure>[1]
    }) => createStructure(facilityId, payload),
    onSuccess: invalidate,
  })
}

export function useUpdateStructureMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateStructure>[1] }) =>
      updateStructure(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteStructureMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteStructure, onSuccess: invalidate })
}

export function useCreateUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({
      facilityId,
      payload,
    }: {
      facilityId: number
      payload: Parameters<typeof createUnit>[1]
    }) => createUnit(facilityId, payload),
    onSuccess: invalidate,
  })
}

export function useUpdateUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateUnit>[1] }) =>
      updateUnit(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteUnit, onSuccess: invalidate })
}

export function useCreatePositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ unitId, payload }: { unitId: number; payload: Parameters<typeof createPosition>[1] }) =>
      createPosition(unitId, payload),
    onSuccess: invalidate,
  })
}

export function useUpdatePositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updatePosition>[1] }) =>
      updatePosition(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeletePositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deletePosition, onSuccess: invalidate })
}

export function useCreateSeasonFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: createSeasonFacility, onSuccess: invalidate })
}

export function useUpdateSeasonFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateSeasonFacility>[1] }) =>
      updateSeasonFacility(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteSeasonFacilityMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteSeasonFacility, onSuccess: invalidate })
}

export function useCreateSeasonUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: createSeasonUnit, onSuccess: invalidate })
}

export function useUpdateSeasonUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateSeasonUnit>[1] }) =>
      updateSeasonUnit(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteSeasonUnitMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteSeasonUnit, onSuccess: invalidate })
}

export function useCreateSeasonPositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: createSeasonPosition, onSuccess: invalidate })
}

export function useUpdateSeasonPositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateSeasonPosition>[1] }) =>
      updateSeasonPosition(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteSeasonPositionMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteSeasonPosition, onSuccess: invalidate })
}

export function useCreateAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: createAssignment, onSuccess: invalidate })
}

export function useUpdateAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateAssignment>[1] }) =>
      updateAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useDeleteAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: deleteAssignment, onSuccess: invalidate })
}

export function useConfirmAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: confirmAssignment, onSuccess: invalidate })
}

export function useConfirmFamilyAssignmentsMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: confirmFamilyAssignments, onSuccess: invalidate })
}

export function useArriveAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: OpsOccurredAtPayload }) =>
      arriveAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useCheckInAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: OpsOccurredAtPayload }) =>
      checkInAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useCheckOutAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: OpsOccurredAtPayload }) =>
      checkOutAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useCancelConfirmedAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload?: CancelAssignmentPayload }) =>
      cancelConfirmedAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useRelocateAssignmentMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: RelocateAssignmentPayload }) =>
      relocateAssignment(id, payload),
    onSuccess: invalidate,
  })
}

export function useGrantFacilityScopeMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: grantFacilityScope, onSuccess: invalidate })
}

export function useRevokeFacilityScopeMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: revokeFacilityScope, onSuccess: invalidate })
}

export function useGrantSupervisorScopeMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: grantSupervisorScope, onSuccess: invalidate })
}

export function useRevokeSupervisorScopeMutation() {
  const invalidate = useInvalidateAccommodation()
  return useMutation({ mutationFn: revokeSupervisorScope, onSuccess: invalidate })
}
