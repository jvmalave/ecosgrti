import { TestBed } from '@angular/core/testing';

import { RequirementRoleService } from './requirement-role.service';

describe('RequirementRoleService', () => {
  let service: RequirementRoleService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(RequirementRoleService);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
